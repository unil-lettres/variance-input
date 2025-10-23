<?php

namespace App\View\Components;

use App\Models\Comparison;
use App\Models\Version;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class Editor extends Component
{

    /**
     * Create a new component instance.
     */
    public function __construct(
        public Version $version,
        public Comparison $comparison,
        public string $xmlContent,
        public bool $isSource,
        public bool $isPublished,
        public bool $canEdit,
        public array $imagesData,
        public string $urlFileSave,
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
