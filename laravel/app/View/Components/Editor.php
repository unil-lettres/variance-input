<?php

namespace App\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class Editor extends Component
{

    /**
     * Create a new component instance.
     */
    public function __construct(
        public string $xmlContent,
        public array $imagesData,
        public string $urlFileSave,
        public bool $canEdit,
    )
    { }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View|Closure|string
    {
        return view('components.editor');
    }
}
