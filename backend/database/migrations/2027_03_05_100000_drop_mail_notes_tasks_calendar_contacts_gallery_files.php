<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Finance-only pivot (round 2): Mail, Notes, Tasks (calendar VTODOs), Calendar,
 * Contacts, Gallery and Files are removed entirely, on top of the earlier
 * Health/Explore/Contacts-v1/Passwords removals. Only Finance, Servers and the
 * shared infra (auth, backup, notifications, Paperless, security portal)
 * remain. Drop their relational tables — children before parents to respect
 * foreign keys.
 *
 * Also drops a handful of long-dead tables from the pre-Ledgerline schema
 * (an early CRM-style prototype) that were never cleaned up: no model or
 * controller in the current codebase references them (verified by grep before
 * writing this migration).
 *
 * This is a destructive, one-way migration — there is no down().
 */
return new class extends Migration
{
    public function up(): void
    {
        // --- Mail archive (incl. shared Mail+Files PGP/S-MIME key material) ---
        Schema::dropIfExists('mail_label_message');
        Schema::dropIfExists('mail_labels');
        Schema::dropIfExists('mail_attachments');
        Schema::dropIfExists('mail_blobs');
        Schema::dropIfExists('mail_drafts');
        Schema::dropIfExists('mail_logs');
        Schema::dropIfExists('mail_saved_searches');
        Schema::dropIfExists('mail_account_signatures');
        Schema::dropIfExists('mail_signatures');
        Schema::dropIfExists('mail_rules');
        Schema::dropIfExists('mail_sync_state');
        Schema::dropIfExists('mail_messages');
        Schema::dropIfExists('mail_pgp_keys');
        Schema::dropIfExists('mail_accounts');
        Schema::dropIfExists('crypto_recipients');
        Schema::dropIfExists('key_servers');

        // --- Calendar + Tasks (VTODOs live on calendars) ---
        Schema::dropIfExists('calendar_alarm_log');
        Schema::dropIfExists('calendar_todo_changes');
        Schema::dropIfExists('calendar_todos');
        Schema::dropIfExists('calendar_changes');
        Schema::dropIfExists('calendar_shares');
        Schema::dropIfExists('calendar_events');
        Schema::dropIfExists('calendars');
        Schema::dropIfExists('dav_changes'); // shared CardDAV/CalDAV sync log

        // --- Contacts ---
        Schema::dropIfExists('address_book_shares');
        Schema::dropIfExists('contact_duplicate_dismissals');
        Schema::dropIfExists('contact_group');
        Schema::dropIfExists('contact_sync_remote_cards');
        Schema::dropIfExists('contact_sync_sources');
        Schema::dropIfExists('contact_versions');
        Schema::dropIfExists('contacts');
        Schema::dropIfExists('contact_groups');
        Schema::dropIfExists('address_books');

        // --- Gallery ---
        Schema::dropIfExists('gallery_album_photo');
        Schema::dropIfExists('gallery_photo_comments');
        Schema::dropIfExists('gallery_photo_reactions');
        Schema::dropIfExists('gallery_faces');
        Schema::dropIfExists('gallery_people');
        Schema::dropIfExists('gallery_internal_shares');
        Schema::dropIfExists('gallery_public_shares');
        Schema::dropIfExists('gallery_upload_links');
        Schema::dropIfExists('gallery_albums');
        Schema::dropIfExists('gallery_photos');

        // --- Files (incl. external S3/SFTP mounts) ---
        Schema::dropIfExists('file_activities');
        Schema::dropIfExists('file_label_file');
        Schema::dropIfExists('file_labels');
        Schema::dropIfExists('file_shares');
        Schema::dropIfExists('file_upload_links');
        Schema::dropIfExists('file_versions');
        Schema::dropIfExists('folder_share_members');
        Schema::dropIfExists('folder_shares');
        Schema::dropIfExists('file_folders');
        Schema::dropIfExists('files');
        Schema::dropIfExists('storage_mounts');

        // --- Notes ---
        Schema::dropIfExists('note_attachments');
        Schema::dropIfExists('note_links');
        Schema::dropIfExists('notes');
        Schema::dropIfExists('note_folders');

        // --- Deadlines (scanned files/mail/gallery/finance text; with three of
        // its four sources gone, the feature is retired too) ---
        Schema::dropIfExists('document_deadlines');

        // --- Cross-module async ZIP export queue, exclusively for gallery+files ---
        Schema::dropIfExists('exports');

        // --- Pre-existing dead schema (pre-Ledgerline CRM prototype; unrelated
        // to the modules above, never referenced by any current model or
        // controller — bonus cleanup found while computing the live table set
        // for this migration) ---
        Schema::dropIfExists('project_tag');
        Schema::dropIfExists('folder_tag');
        Schema::dropIfExists('file_tag');
        Schema::dropIfExists('contact_emails');
        Schema::dropIfExists('contact_phones');
        Schema::dropIfExists('branches');
        Schema::dropIfExists('projects');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('folders');
        Schema::dropIfExists('customers');
        Schema::dropIfExists('income_entries');
        Schema::dropIfExists('invoice_lines');
        Schema::dropIfExists('invoice_number_sequences');
        Schema::dropIfExists('company_profiles');
        Schema::dropIfExists('time_entries');
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('units');
        Schema::dropIfExists('dav_credentials');
        Schema::dropIfExists('calendar_objects');

        // --- app_settings: columns that only ever configured a now-removed
        // module (VirusTotal scanned FileEntry+MailAttachment; ml_* configured
        // Gallery's CLIP/face recognition; the mail_* / files_quota_mb limits
        // configured the Mail archive and Files quota respectively).
        // files_max_upload_mb / files_blob_orphan_grace_hours are a second,
        // slightly older casualty of the same pivot: AppServiceProvider's
        // SETTING_OVERRIDES stopped reading them earlier in this pivot (the
        // Files disk max-upload size is env-only now, config/files.php), which
        // left these two columns declared on the model but with zero readers
        // anywhere in the app. ---
        $appSettingsDead = [
            'virustotal_api_key',
            'ml_enabled', 'ml_face_enabled', 'ml_url', 'ml_clip_model', 'ml_face_model',
            'ml_search_distance', 'ml_dup_distance', 'ml_face_min_score', 'ml_face_match_distance',
            'files_quota_mb', 'mail_log_retention_days', 'mail_blob_orphan_grace_hours',
            'files_max_upload_mb', 'files_blob_orphan_grace_hours',
        ];
        $appSettingsDead = array_values(array_filter(
            $appSettingsDead,
            static fn (string $c): bool => Schema::hasColumn('app_settings', $c),
        ));
        if ($appSettingsDead !== []) {
            Schema::table('app_settings', function (Blueprint $table) use ($appSettingsDead): void {
                $table->dropColumn($appSettingsDead);
            });
        }

        // --- user_settings: per-user preference columns for the removed
        // modules (Files version-history depth, Calendar default view/week
        // start, Mail reader/signature/avatar/column prefs, and the
        // Health/Explore unit preferences — those two modules were already
        // removed in an earlier pivot but their preference columns and the
        // "Appearance" unit selects that wrote to them were never cleaned up). ---
        $userSettingsDead = [
            'file_max_versions',
            'calendar_default_view', 'calendar_week_start',
            'mail_load_remote', 'mail_allow_scripts', 'mail_signature', 'mail_avatars', 'mail_columns',
            'unit_distance', 'unit_elevation', 'unit_weight', 'unit_temp', 'unit_glucose',
        ];
        $userSettingsDead = array_values(array_filter(
            $userSettingsDead,
            static fn (string $c): bool => Schema::hasColumn('user_settings', $c),
        ));
        if ($userSettingsDead !== []) {
            Schema::table('user_settings', function (Blueprint $table) use ($userSettingsDead): void {
                $table->dropColumn($userSettingsDead);
            });
        }

        // --- users.webdav_password: only ever written by the removed WebDAV
        // auth module (App\Dav\WebDavAuth, gone) and the block-user flow that
        // revoked it; now write-never. ---
        if (Schema::hasColumn('users', 'webdav_password')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropColumn('webdav_password');
            });
        }
    }

    public function down(): void
    {
        // Irreversible: these modules are gone for good.
    }
};
