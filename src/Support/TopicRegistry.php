<?php

namespace Asciisd\NovaChat\Support;

use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Response;

class TopicRegistry
{
    /** @var array<string, TopicDescriptor>|null */
    protected ?array $cache = null;

    /** @return array<string, TopicDescriptor> */
    public function all(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $configured = (array) config('nova-chat.topics', []);

        return $this->cache = collect($configured)
            ->mapWithKeys(fn (array $config, string $key) => [$key => TopicDescriptor::fromConfig($key, $config)])
            ->all();
    }

    public function find(string $key): TopicDescriptor
    {
        $topic = $this->all()[$key] ?? null;

        if (! $topic) {
            throw new HttpResponseException(Response::json(['message' => "Unknown chat topic [{$key}]"], 404));
        }

        return $topic;
    }

    public function default(): TopicDescriptor
    {
        $topics = $this->all();

        foreach ($topics as $topic) {
            if ($topic->default) {
                return $topic;
            }
        }

        $first = reset($topics);

        if (! $first) {
            throw new HttpResponseException(Response::json(['message' => 'No chat topics configured'], 404));
        }

        return $first;
    }

    public function collection(): Collection
    {
        return collect($this->all())->values();
    }
}
