<?php

namespace Asciisd\NovaChat;

use Illuminate\Http\Request;
use Laravel\Nova\Menu\MenuSection;
use Laravel\Nova\Nova;
use Laravel\Nova\Tool;

class NovaChat extends Tool
{
    public function boot(): void
    {
        Nova::script('nova-chat', __DIR__ . '/../dist/js/tool.js');
        Nova::style('nova-chat', __DIR__ . '/../dist/js/tool.css');
    }

    public function menu(Request $request): MenuSection
    {
        return MenuSection::make('Chat')
            ->path('/nova-chat')
            ->icon('chat-bubble-left-right');
    }
}
