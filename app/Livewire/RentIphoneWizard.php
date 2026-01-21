<?php

namespace App\Livewire;

use Livewire\Attributes\On;
use Livewire\Component;

class RentIphoneWizard extends Component
{
    public int $step = 1;

    // STEP 1
    public ?int $selectedIphoneId = null;

    // STEP 2
    public ?int $rentHours = null;

    // STEP 3
    public string $customerName = '';
    public string $customerPhone = '';

    #[On('iphone-selected')]
    public function setIphone(int $iphoneId)
    {
        $this->selectedIphoneId = $iphoneId;
    }
    
    protected function rules(): array
    {
        return match ($this->step) {
            1 => [
                'selectedIphoneId' => 'required|exists:iphones,id',
            ],
            2 => [
                'rentHours' => 'required|integer|min:1',
            ],
            3 => [
                'customerName' => 'required|min:3',
                'customerPhone' => 'required',
            ],
            default => [],
        };
    }

    public function next(): void
    {
        $this->validate();

        if ($this->step < 4) {
            $this->step++;
        }
    }

    public function back(): void
    {
        if ($this->step > 1) {
            $this->step--;
        }
    }

    public function submit(): void
    {
        $this->validate();

        // Rental::create([...]);

        session()->flash('success', 'Sewa berhasil dibuat');
    }

    public function render()
    {
        return view('livewire.rent-iphone-wizard');
    }
}
