<?php

namespace App\Console\Commands;

use App\Blog\PostRepository;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

#[Signature('post:new {title : The post title} {--date= : Publish date (defaults to now)}')]
#[Description('Create a new post file (resources/posts/YYYY-MM-DD-N.md)')]
class MakePost extends Command
{
    public function handle(PostRepository $posts): int
    {
        $date = $this->option('date')
            ? CarbonImmutable::parse((string) $this->option('date'))
            : CarbonImmutable::now();

        $id = $posts->nextIdFor($date);
        $path = $posts->path()."/{$id}.md";
        // Single-quoted YAML (an apostrophe is escaped by doubling it) to match the formatter's singleQuote style.
        $title = str_replace("'", "''", (string) $this->argument('title'));

        File::ensureDirectoryExists($posts->path());
        File::put($path, <<<MD
            ---
            title: '{$title}'
            published: {$date->format('Y-m-d H:i:s')}
            tags: []
            ---

            Write your post here.

            MD);

        $this->components->info("Created [{$path}].");

        return self::SUCCESS;
    }
}
