<?php

namespace Tests;

use App\Models\Author;
use App\Models\Permission;
use App\Models\User;
use App\Models\Work;
use App\Models\Version;
use App\Models\Comparison;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\File;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        $app = require __DIR__.'/../bootstrap/app.php';
        $app->useStoragePath(__DIR__.'/../storage/framework/testing');
        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->prepareVarianceFilesystem();
    }

    protected function signInEditor(?User $user = null): User
    {
        $user ??= User::factory()->create([
            'name' => 'editor',
            'full_name' => 'Editorial Tester',
            'is_admin' => false,
        ]);

        $this->actingAs($user);

        return $user;
    }

    protected function signInAdmin(?User $user = null): User
    {
        $user ??= User::factory()->create([
            'name' => 'admin',
            'full_name' => 'Admin Tester',
            'is_admin' => true,
        ]);

        $this->actingAs($user);

        return $user;
    }

    protected function grantAuthorEditPermission(User $user, Author $author): Permission
    {
        return Permission::firstOrCreate([
            'user_id' => $user->id,
            'author_id' => $author->id,
            'work_id' => null,
            'permission_type' => 'edit',
        ]);
    }

    protected function grantWorkEditPermission(User $user, Work $work): Permission
    {
        return Permission::firstOrCreate([
            'user_id' => $user->id,
            'author_id' => null,
            'work_id' => $work->id,
            'permission_type' => 'edit',
        ]);
    }

    protected function grantWorkVersionEditorPermission(User $user, Work $work): Permission
    {
        return Permission::firstOrCreate([
            'user_id' => $user->id,
            'author_id' => null,
            'work_id' => $work->id,
            'permission_type' => User::PERMISSION_VERSION_EDITOR,
        ]);
    }

    protected function createEditableWork(User $user, array $author = [], array $work = []): Work
    {
        $authorModel = Author::factory()->create($author);
        $this->grantAuthorEditPermission($user, $authorModel);

        $workModel = Work::factory()->for($authorModel)->create($work);
        $workModel->workStatus()->create([]);

        return $workModel;
    }

    protected function prepareVarianceFilesystem(): void
    {
        $paths = [
            storage_path('app/public/uploads/versions'),
            storage_path('app/private/lignes'),
            storage_path('app/private/pagination'),
            storage_path('app/private/reader_cache'),
            storage_path('app/private/cache/version-editor'),
            storage_path('app/tmp/pager'),
            storage_path('framework/views'),
            public_path('uploads'),
            base_path('../variance/uploads'),
            '/var/www/variance/uploads',
        ];

        foreach ($paths as $path) {
            $this->ensureDirectoryExistsWhenWritable($path);
        }

        $this->cleanDirectoryIfPresent(storage_path('app/private/pagination'));
        $this->cleanDirectoryIfPresent(storage_path('app/private/reader_cache'));
        $this->cleanDirectoryIfPresent(storage_path('app/tmp/pager'));
        $this->cleanDirectoryIfPresent(storage_path('app/private/cache/version-editor'));
        $this->cleanDirectoryIfPresent(storage_path('framework/views'));
    }

    protected function ensureDirectoryExistsWhenWritable(string $path): void
    {
        if (File::isDirectory($path)) {
            return;
        }

        $anchor = dirname($path);
        while (!is_dir($anchor) && $anchor !== dirname($anchor)) {
            $anchor = dirname($anchor);
        }

        if (!is_dir($anchor) || !is_writable($anchor)) {
            return;
        }

        File::ensureDirectoryExists($path);
    }

    protected function cleanDirectoryIfPresent(string $path): void
    {
        if (!File::isDirectory($path)) {
            return;
        }

        File::cleanDirectory($path);
    }

    protected function writeVersionXml(Version $version, string $body = '<p>Texte témoin</p>'): string
    {
        $path = storage_path("app/public/uploads/versions/{$version->folder}.xml");
        File::ensureDirectoryExists(dirname($path));
        File::put(
            $path,
            <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<TEI xmlns="http://www.tei-c.org/ns/1.0">
  <text>
    <body>
      <div>
        {$body}
      </div>
    </body>
  </text>
</TEI>
XML
        );

        return $path;
    }

    protected function writeComparisonArtifacts(Comparison $comparison, array $contents = []): string
    {
        $comparison->loadMissing('sourceVersion.work.author');
        $authorFolder = $comparison->sourceVersion->work->author->folder;
        $workFolder = $comparison->sourceVersion->work->folder;
        $dir = storage_path("app/public/uploads/{$authorFolder}/{$workFolder}/comparisons/{$comparison->id}");

        File::ensureDirectoryExists($dir);

        $defaults = [
            'd.xhtml' => '<div><p><li>Suppression</li></p></div>',
            'i.xhtml' => '<div><p><li>Insertion</li></p></div>',
            'r.xhtml' => '<div><p><li>Remplacement</li></p></div>',
            's.xhtml' => '<div><p><li>Déplacement</li></p></div>',
            'source.xhtml' => '<div><p>Source</p></div>',
            'target.xhtml' => '<div><p>Cible</p></div>',
        ];

        foreach (array_replace($defaults, $contents) as $name => $content) {
            File::put($dir . DIRECTORY_SEPARATOR . $name, $content);
        }

        return $dir;
    }

    protected function writeFacsimilePair(Version $version, string $basename = '001'): array
    {
        $version->loadMissing('work.author');
        $authorFolder = $version->work->author->folder;
        $workFolder = $version->work->folder;
        $dir = storage_path("app/public/uploads/{$authorFolder}/{$workFolder}/{$version->folder}");

        File::ensureDirectoryExists($dir);

        $jpg = base64_decode('/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRofHh0aHBwgJC4nICIsIxwcKDcpLDAx
NDQ0Hyc5PTgyPC4zNDL/2wBDAQkJCQwLDBgNDRgyIRwhMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjL/wAAR
CAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAX/xAAdEAACAQQDAAAAAAAAAAAAAAABAgMABBEFEiEx/8QAFQEBAQAAAAAAAAAAAAAA
AAAAAgP/xAAVEQEBAAAAAAAAAAAAAAAAAAAAEf/aAAwDAQACEQMRAD8Aq4rQmV2LJ5QkGqv/2Q==', true);

        $main = "img_{$version->folder}_{$basename}.jpg";
        $thumb = "img_{$version->folder}_{$basename}_thumb.jpg";
        File::put($dir . DIRECTORY_SEPARATOR . $main, $jpg);
        File::put($dir . DIRECTORY_SEPARATOR . $thumb, $jpg);

        return [
            'dir' => $dir,
            'main' => $main,
            'thumb' => $thumb,
        ];
    }
}
