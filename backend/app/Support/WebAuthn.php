<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use App\Models\WebauthnCredential;
use Cose\Algorithms;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Symfony\Component\Serializer\SerializerInterface;
use Webauthn\AttestationStatement\AttestationStatementSupportManager;
use Webauthn\AttestationStatement\NoneAttestationStatementSupport;
use Webauthn\AuthenticatorAssertionResponse;
use Webauthn\AuthenticatorAssertionResponseValidator;
use Webauthn\AuthenticatorAttestationResponse;
use Webauthn\AuthenticatorAttestationResponseValidator;
use Webauthn\AuthenticatorSelectionCriteria;
use Webauthn\CeremonyStep\CeremonyStepManager;
use Webauthn\CeremonyStep\CeremonyStepManagerFactory;
use Webauthn\Denormalizer\WebauthnSerializerFactory;
use Webauthn\PublicKeyCredential;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialDescriptor;
use Webauthn\PublicKeyCredentialParameters;
use Webauthn\PublicKeyCredentialRequestOptions;
use Webauthn\PublicKeyCredentialRpEntity;
use Webauthn\PublicKeyCredentialSource;
use Webauthn\PublicKeyCredentialUserEntity;

/**
 * Thin WebAuthn (passkey / FIDO2 hardware key) service over web-auth/webauthn-lib
 * 5.3. Bearer-SPA friendly: challenges live in the cache (single-use, 5 min),
 * credentials are stored as serialized PublicKeyCredentialSource (public-key only,
 * no secret at rest). None-attestation (no authenticator fingerprinting).
 */
class WebAuthn
{
    private const CHALLENGE_TTL = 300;

    public function enabled(): bool
    {
        return is_string(config('webauthn.rp_id')) && config('webauthn.rp_id') !== '';
    }

    // ---- Registration (authenticated) ----

    /** Creation options JSON for navigator.credentials.create(); caches the challenge per user. */
    public function registerOptions(User $user): string
    {
        $rp = PublicKeyCredentialRpEntity::create($this->rpName(), $this->rpId());
        $userEntity = PublicKeyCredentialUserEntity::create((string) $user->email, (string) $user->id, $user->name);
        $params = [
            PublicKeyCredentialParameters::createPk(Algorithms::COSE_ALGORITHM_ES256),
            PublicKeyCredentialParameters::createPk(Algorithms::COSE_ALGORITHM_RS256),
        ];
        $exclude = WebauthnCredential::query()->where('user_id', $user->id)->pluck('credential_id')
            ->map(fn (mixed $id): PublicKeyCredentialDescriptor => PublicKeyCredentialDescriptor::create(
                PublicKeyCredentialDescriptor::CREDENTIAL_TYPE_PUBLIC_KEY,
                self::b64uDecode(is_string($id) ? $id : ''),
            ))->all();

        $options = new PublicKeyCredentialCreationOptions(
            rp: $rp,
            user: $userEntity,
            challenge: random_bytes(32),
            pubKeyCredParams: $params,
            authenticatorSelection: new AuthenticatorSelectionCriteria(
                userVerification: AuthenticatorSelectionCriteria::USER_VERIFICATION_REQUIREMENT_PREFERRED,
                residentKey: AuthenticatorSelectionCriteria::RESIDENT_KEY_REQUIREMENT_PREFERRED,
            ),
            attestation: PublicKeyCredentialCreationOptions::ATTESTATION_CONVEYANCE_PREFERENCE_NONE,
            excludeCredentials: $exclude,
        );
        $json = $this->serializer()->serialize($options, 'json');
        Cache::put($this->regKey($user), $json, self::CHALLENGE_TTL);

        return $json;
    }

    /**
     * Verify an attestation response and persist the credential.
     *
     * @return WebauthnCredential the newly stored passkey
     */
    public function verifyRegistration(User $user, string $credentialJson, ?string $label): WebauthnCredential
    {
        $optionsJson = Cache::pull($this->regKey($user));
        abort_if(! is_string($optionsJson), 400, 'challenge_expired');
        $options = $this->serializer()->deserialize($optionsJson, PublicKeyCredentialCreationOptions::class, 'json');

        $pkc = $this->serializer()->deserialize($credentialJson, PublicKeyCredential::class, 'json');
        abort_unless($pkc->response instanceof AuthenticatorAttestationResponse, 422, 'not_attestation');

        $record = AuthenticatorAttestationResponseValidator::create($this->creationCeremony())
            ->check($pkc->response, $options, $this->rpId());
        $source = PublicKeyCredentialSource::fromCredentialRecord($record);

        // credential_id/source/aaguid/user_id are server-derived, never mass-assignable.
        $cred = new WebauthnCredential;
        $cred->forceFill([
            'user_id' => $user->id,
            'credential_id' => self::b64uEncode($source->publicKeyCredentialId),
            'name' => $label !== null && trim($label) !== '' ? mb_substr(trim($label), 0, 120) : 'Passkey',
            'source' => $this->serializer()->serialize($source, 'json'),
            'aaguid' => $source->aaguid->toString(),
            'last_used_at' => now(),
        ])->save();

        return $cred;
    }

    // ---- Authentication (public) ----

    /** @return array{handle:string, options:string} request options + a cache handle for verify. */
    public function loginOptions(): array
    {
        $options = new PublicKeyCredentialRequestOptions(
            challenge: random_bytes(32),
            rpId: $this->rpId(),
            userVerification: PublicKeyCredentialRequestOptions::USER_VERIFICATION_REQUIREMENT_PREFERRED,
        );
        $handle = Str::uuid()->toString();
        $json = $this->serializer()->serialize($options, 'json');
        Cache::put($this->authKey($handle), $json, self::CHALLENGE_TTL);

        return ['handle' => $handle, 'options' => $json];
    }

    /** Verify an assertion; returns the owning user on success, or null. */
    public function verifyAssertion(string $handle, string $credentialJson): ?User
    {
        $optionsJson = Cache::pull($this->authKey($handle));
        if (! is_string($optionsJson)) {
            return null;
        }
        $options = $this->serializer()->deserialize($optionsJson, PublicKeyCredentialRequestOptions::class, 'json');
        $pkc = $this->serializer()->deserialize($credentialJson, PublicKeyCredential::class, 'json');
        if (! $pkc->response instanceof AuthenticatorAssertionResponse) {
            return null;
        }
        $row = WebauthnCredential::query()->withoutGlobalScopes()
            ->where('credential_id', self::b64uEncode($pkc->rawId))->first();
        if (! $row instanceof WebauthnCredential) {
            return null;
        }
        $source = $this->serializer()->deserialize($row->source, PublicKeyCredentialSource::class, 'json');
        try {
            $updated = AuthenticatorAssertionResponseValidator::create($this->requestCeremony())
                ->check($source, $pkc->response, $options, $this->rpId(), $source->userHandle);
        } catch (\Throwable) {
            return null;
        }
        // Persist the advanced signature counter + last-used.
        $row->forceFill([
            'source' => $this->serializer()->serialize(PublicKeyCredentialSource::fromCredentialRecord($updated), 'json'),
            'last_used_at' => now(),
        ])->save();

        return User::query()->find($row->user_id);
    }

    // ---- internals ----

    private function serializer(): SerializerInterface
    {
        $asm = AttestationStatementSupportManager::create();
        $asm->add(NoneAttestationStatementSupport::create());

        return (new WebauthnSerializerFactory($asm))->create();
    }

    private function creationCeremony(): CeremonyStepManager
    {
        return $this->csmFactory()->creationCeremony();
    }

    private function requestCeremony(): CeremonyStepManager
    {
        return $this->csmFactory()->requestCeremony();
    }

    private function csmFactory(): CeremonyStepManagerFactory
    {
        $f = new CeremonyStepManagerFactory;
        $f->setAllowedOrigins($this->origins());
        $f->setSecuredRelyingPartyId([$this->rpId()]);

        return $f;
    }

    private function rpId(): string
    {
        $v = config('webauthn.rp_id');

        return is_string($v) ? $v : 'localhost';
    }

    private function rpName(): string
    {
        $v = config('webauthn.rp_name');

        return is_string($v) ? $v : 'Ledgerline';
    }

    /** @return list<string> */
    private function origins(): array
    {
        $v = config('webauthn.origins');

        return is_array($v) ? array_values(array_filter($v, 'is_string')) : [];
    }

    private function regKey(User $user): string
    {
        return 'webauthn:reg:'.$user->id;
    }

    private function authKey(string $handle): string
    {
        return 'webauthn:auth:'.$handle;
    }

    private static function b64uEncode(string $bin): string
    {
        return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
    }

    private static function b64uDecode(string $s): string
    {
        return (string) base64_decode(strtr($s, '-_', '+/'), true);
    }
}
