<?php

namespace Asciisd\NovaChat\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

use function Laravel\Prompts\suggest;
use function Laravel\Prompts\text;

class MakeTableCommand extends Command
{
    protected $signature = 'nova-chat:make-table
        {table? : The chat-message table name (e.g. order_messages)}
        {--host= : Fully qualified host model class for FK inference}
        {--fk= : Foreign key column name on the message table}
        {--force : Overwrite an existing migration with the same name}';

    protected $description = 'Generate a chat message table migration from the package stub.';

    public function handle(): int
    {
        $table = $this->resolveTable();
        $host = $this->resolveHost();
        $foreignKey = $this->resolveForeignKey($host);

        $stub = $this->loadStub();

        if ($stub === null) {
            $this->components->error('Could not find chat_messages_table.stub in the package.');

            return self::FAILURE;
        }

        $migration = $this->renderMigration($stub, $table, $foreignKey);

        $targetPath = $this->targetPath($table);

        if ($this->migrationAlreadyExists($table) && ! $this->option('force')) {
            $this->components->error(
                "A migration for [{$table}] already exists. Re-run with --force to overwrite."
            );

            return self::FAILURE;
        }

        if (! is_dir(dirname($targetPath))) {
            @mkdir(dirname($targetPath), 0755, true);
        }

        file_put_contents($targetPath, $migration);

        $this->components->info("Created migration: {$targetPath}");
        $this->printNextSteps($table, $host);

        return self::SUCCESS;
    }

    protected function resolveTable(): string
    {
        $table = (string) ($this->argument('table') ?: '');

        if ($table === '') {
            $table = text(
                label: 'Chat message table name',
                placeholder: 'order_messages',
                default: 'chat_messages',
                required: true,
                hint: 'Conventionally <host_singular>_messages.',
            );
        }

        return Str::snake(trim($table));
    }

    /**
     * Resolve the host model class. Returns null when the developer skips the
     * suggestion (e.g. using a generic stub without inferring the FK column).
     */
    protected function resolveHost(): ?string
    {
        if ($host = $this->option('host')) {
            return ltrim((string) $host, '\\');
        }

        $candidates = $this->topicHostCandidates();

        $value = suggest(
            label: 'Host model class (optional, used to infer the FK column)',
            options: $candidates,
            placeholder: 'App\\Models\\Order',
            hint: 'Leave blank to keep the generic chattable_id column.',
        );

        $value = trim((string) $value);

        return $value === '' ? null : ltrim($value, '\\');
    }

    protected function resolveForeignKey(?string $host): string
    {
        if ($fk = $this->option('fk')) {
            return Str::snake((string) $fk);
        }

        $default = $host ? $this->inferForeignKey($host) : 'chattable_id';

        $fk = text(
            label: 'Foreign key column on the message table',
            placeholder: $default,
            default: $default,
            required: true,
        );

        return Str::snake(trim($fk));
    }

    protected function inferForeignKey(string $host): string
    {
        if (class_exists($host)) {
            try {
                /** @var \Illuminate\Database\Eloquent\Model $instance */
                $instance = new $host;
                $table = $instance->getTable();

                return Str::singular($table) . '_id';
            } catch (\Throwable) {
                // Fall through to class-name inference.
            }
        }

        return Str::snake(class_basename($host)) . '_id';
    }

    protected function loadStub(): ?string
    {
        $candidates = [
            __DIR__ . '/../../../database/stubs/chat_messages_table.stub',
            base_path('database/stubs/nova-chat/chat_messages_table.stub'),
        ];

        foreach ($candidates as $path) {
            if (is_file($path)) {
                return (string) file_get_contents($path);
            }
        }

        return null;
    }

    protected function renderMigration(string $stub, string $table, string $foreignKey): string
    {
        $replacements = [
            "Schema::create('chat_messages'" => "Schema::create('{$table}'",
            "Schema::dropIfExists('chat_messages')" => "Schema::dropIfExists('{$table}')",
            "'chattable_id'" => "'{$foreignKey}'",
            "['chattable_id', 'created_at']" => "['{$foreignKey}', 'created_at']",
            "['chattable_id', 'is_from_admin', 'read_at']" => "['{$foreignKey}', 'is_from_admin', 'read_at']",
            "'chat_messages_unread_idx'" => "'{$table}_unread_idx'",
        ];

        $rendered = strtr($stub, $replacements);

        return $this->stripStubGuidance($rendered);
    }

    /**
     * Remove the stub's hand-edit guidance — the command already did the
     * replacement, so leaving "Replace `chat_messages` …" in the generated
     * migration would be misleading.
     */
    protected function stripStubGuidance(string $contents): string
    {
        $contents = preg_replace(
            '#^/\*\*.*?Reference migration.*?\*/\s*#ms',
            '',
            $contents,
            1,
        );

        $contents = preg_replace(
            '#^\s*//\s*Replace this with the foreign key to your host model\.\s*\n#m',
            '',
            (string) $contents,
        );

        return (string) $contents;
    }

    protected function targetPath(string $table): string
    {
        $timestamp = now()->format('Y_m_d_His');

        return base_path("database/migrations/{$timestamp}_create_{$table}_table.php");
    }

    protected function migrationAlreadyExists(string $table): bool
    {
        $directory = base_path('database/migrations');

        if (! is_dir($directory)) {
            return false;
        }

        foreach ((array) glob($directory . '/*_create_' . $table . '_table.php') as $existing) {
            if (is_file($existing)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, string>
     */
    protected function topicHostCandidates(): array
    {
        $topics = (array) config('nova-chat.topics', []);

        $candidates = [];

        foreach ($topics as $topic) {
            if (is_array($topic) && isset($topic['model']) && is_string($topic['model'])) {
                $candidates[] = ltrim($topic['model'], '\\');
            }
        }

        return array_values(array_unique($candidates));
    }

    protected function printNextSteps(string $table, ?string $host): void
    {
        $this->newLine();
        $this->components->info('Next steps:');
        $this->components->bulletList([
            "Run `php artisan migrate` to create the {$table} table.",
            'Create the message model and have it implement '
                . "Asciisd\\NovaChat\\Contracts\\ChatMessage and use AsChatMessage.",
            $host
                ? "Make {$host} implement Asciisd\\NovaChat\\Contracts\\Chattable and use HasChat."
                : 'Make the host model implement Asciisd\\NovaChat\\Contracts\\Chattable and use HasChat.',
            'Register the topic in config/nova-chat.php (model + message_model).',
            'Add morph_map entries for the host and the participant classes.',
        ]);
    }
}
