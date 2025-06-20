<?php

namespace App\Livewire\Iphones;

use App\Models\Gallery;
use Livewire\Component;

class Edit extends Component
{
    public $iphone,
        $name,
        $description,
        $urlPoster,
        $date,
        $slug,
        $gallery_id;

    public function mount($iphone)
    {
        $this->iphone = $iphone;
        $this->name = $iphone->name;
        $this->description = $iphone->description;
        $this->date = $iphone->created;
        $this->slug = $iphone->slug;
        $this->gallery_id = Gallery::where('id', $this->iphone->gallery_id)->select('image')->first()?->image;
    }

    public function render()
    {
        return view('livewire.iphones.edit', [
            'iphone' => $this->iphone
        ]);
    }
}
