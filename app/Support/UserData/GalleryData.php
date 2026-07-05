<?php

declare(strict_types=1);

namespace App\Support\UserData;

use App\Models\Album;
use App\Models\Face;
use App\Models\Person;
use App\Models\Photo;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Gallery module contribution to per-user GDPR export and account erasure.
 *
 * Photos own their data via the `uploaded_by` column; albums, people and faces
 * own theirs via `user_id`. The export carries photo metadata only (never image
 * bytes); the purge deletes every stored blob — original, thumbnail, medium
 * rendition and extracted motion clip for photos, plus the face crop thumbnails
 * — before dropping the rows, album membership pivots, albums and people/faces.
 */
final class GalleryData implements UserDataContributor
{
    public function key(): string
    {
        return 'gallery';
    }

    public function export(User $user): array
    {
        $albumNamesByPhoto = DB::table('album_photo')
            ->join('albums', 'albums.id', '=', 'album_photo.album_id')
            ->where('albums.user_id', $user->getKey())
            ->orderBy('albums.name')
            ->get(['album_photo.photo_id', 'albums.name'])
            ->groupBy('photo_id')
            ->map(fn ($rows) => $rows->pluck('name')->all());

        $personNamesByPhoto = Face::query()
            ->withoutGlobalScopes()
            ->where('faces.user_id', $user->getKey())
            ->whereNotNull('person_id')
            ->join('people', 'people.id', '=', 'faces.person_id')
            ->whereNotNull('people.name')
            ->orderBy('people.name')
            ->get(['faces.photo_id', 'people.name'])
            ->groupBy('photo_id')
            ->map(fn ($rows) => $rows->pluck('name')->unique()->values()->all());

        $photos = Photo::query()
            ->withoutGlobalScopes()
            ->withTrashed()
            ->where('uploaded_by', $user->getKey())
            ->orderBy('id')
            ->get([
                'id',
                'name',
                'original_name',
                'media_type',
                'mime_type',
                'size',
                'width',
                'height',
                'duration',
                'latitude',
                'longitude',
                'place',
                'camera',
                'favorited_at',
                'taken_at',
                'created_at',
                'updated_at',
                'deleted_at',
            ])
            ->map(function (Photo $photo) use ($albumNamesByPhoto, $personNamesByPhoto): array {
                $row = $photo->attributesToArray();
                $row['dimensions'] = $photo->width !== null && $photo->height !== null
                    ? $photo->width.'x'.$photo->height
                    : null;
                $row['favorite'] = $photo->favorited_at !== null;
                $row['albums'] = $albumNamesByPhoto->get($photo->id, []);
                $row['people'] = $personNamesByPhoto->get($photo->id, []);

                return $row;
            })
            ->all();

        $albums = Album::query()
            ->withoutGlobalScopes()
            ->where('user_id', $user->getKey())
            ->orderBy('name')
            ->get(['id', 'name', 'created_at', 'updated_at'])
            ->toArray();

        $people = Person::query()
            ->withoutGlobalScopes()
            ->where('user_id', $user->getKey())
            ->whereNotNull('name')
            ->orderBy('name')
            ->get(['id', 'name', 'faces_count', 'created_at', 'updated_at'])
            ->toArray();

        return [
            'photos' => $photos,
            'albums' => $albums,
            'people' => $people,
        ];
    }

    public function purge(User $user): void
    {
        $disk = Storage::disk(config('files.disk'));

        // Face crop thumbnails + rows first: photos.id cascade-deletes faces at
        // the DB level, so clearing the crop blobs here (before the photo purge)
        // keeps them from being orphaned on disk.
        Face::query()
            ->withoutGlobalScopes()
            ->where('user_id', $user->getKey())
            ->select(['id', 'thumb_path'])
            ->chunkById(500, function ($faces) use ($disk): void {
                foreach ($faces as $face) {
                    if ($face->thumb_path !== null) {
                        $disk->delete($face->thumb_path);
                    }
                }

                Face::query()
                    ->withoutGlobalScopes()
                    ->whereIn('id', $faces->pluck('id'))
                    ->delete();
            });

        // People (faces already gone; cover_face_id/contact_id are plain columns
        // with no FK, so nothing dangles).
        Person::query()
            ->withoutGlobalScopes()
            ->where('user_id', $user->getKey())
            ->delete();

        // Album membership pivots, then the albums themselves.
        $albumIds = Album::query()
            ->withoutGlobalScopes()
            ->where('user_id', $user->getKey())
            ->pluck('id');

        if ($albumIds->isNotEmpty()) {
            DB::table('album_photo')->whereIn('album_id', $albumIds)->delete();

            Album::query()
                ->withoutGlobalScopes()
                ->whereIn('id', $albumIds)
                ->delete();
        }

        // Photo blobs, then the photo rows (chunked, trashed included). This also
        // cascade-deletes any remaining faces and album_photo pivots at the DB
        // level, so the operation stays FK-safe if run more than once.
        Photo::query()
            ->withoutGlobalScopes()
            ->withTrashed()
            ->where('uploaded_by', $user->getKey())
            ->select(['id', 'disk_path', 'thumb_path', 'medium_path', 'motion_path'])
            ->chunkById(200, function ($photos) use ($disk): void {
                foreach ($photos as $photo) {
                    foreach ($photo->allPaths() as $path) {
                        $disk->delete($path);
                    }
                }

                Photo::query()
                    ->withoutGlobalScopes()
                    ->withTrashed()
                    ->whereIn('id', $photos->pluck('id'))
                    ->forceDelete();
            });
    }
}
