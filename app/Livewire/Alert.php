<?php

namespace App\Livewire;

use Livewire\Component;

class Alert extends Component
{

    public $type;
    public $message;
    public $visible = false;

    protected $listeners = ['showAlert'];

    public function showAlert($type, $message)
    {
        $this->type = $type;
        $this->message = $message;
        $this->visible = true;

        // Auto hide setelah 3 detik
        $this->dispatch('alert-shown');
        // $this->dispatch('closeAlert')->self()->after(3000);
    }

    public function closeAlert()
    {
        $this->visible = false;
    }
    
    public function render()
    {
        return view('livewire.alert');
    }
}
