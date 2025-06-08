<?php

namespace App\Livewire\Iphones;

use Jantinnerezo\LivewireAlert\Facades\LivewireAlert;
use Livewire\Component;

class Create extends Component
{
    public $name,
        $urlPoster;

    public function test()
    {
        LivewireAlert::title('Custom Alert')
            ->text('This alert has a unique style.')
            ->success()
            ->withOptions([
                'width' => '1000px',
                'background' => '#000000',
                'customClass' => ['popup' => 'animate__animated
      animate__fadeInUp
      animate__faster'],
                'allowOutsideClick' => false,
            ])->show();
    }

    public function render()
    {
        return view('livewire.iphones.create');
    }
}
