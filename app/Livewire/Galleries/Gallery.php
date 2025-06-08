<?php

namespace App\Livewire\Galleries;

use App\Models\Gallery as ModelsGallery;
use Livewire\Component;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\On;
use Livewire\WithFileUploads;

class Gallery extends Component
{
    use WithFileUploads;

    public $paginate = 20;
    public $images = [];

    // public function updatedImages($props)
    // {
    //     dd($props);
    // }
    public function saveImage()
    {
        if (auth()->user()->can('create')) {
            if (!empty($this->images)) {
                $this->validate([
                    'images.*' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:2048'
                ]);

                foreach ($this->images as $image) {
                    ModelsGallery::create([
                        'image' => $image->store('galleries')
                    ]);
                }

                $this->alert('success', 'upload success');
                $this->dispatch('re-render');
                $this->images = [];
            } else {
                $this->alert('error', 'image tidak ditemukan');
            }
        } else {
            $this->alert('error', 'kamu tidak memiliki izin');
        }
    }

    #[On('delete-poster')]
    public function deletePoster($id)
    {
        if (auth()->user()->can('delete')) {
            # code...
            $gallery = ModelsGallery::find($id);
            $gallery->delete();
            Storage::delete($gallery);
            $this->alert('success', 'image deleted successfully');
            $this->dispatch('re-render');
        } else {
            $this->alert('error', 'kamu tidak memiliki izin');
        }
    }

    #[On('alert-me')]
    public function alertMe($status, $message)
    {
        $this->alert($status, $message);
    }

    public function getGalleries()
    {
        return ModelsGallery::latest('id');
    }

    public function render()
    {
        return view('livewire.galleries.gallery', [
            'galleries' => $this->getGalleries()->paginate($this->paginate)
        ]);
    }
}
