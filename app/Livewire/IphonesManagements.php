<?php

namespace App\Livewire;

use App\Models\Iphones;
use Jantinnerezo\LivewireAlert\Facades\LivewireAlert;
use Livewire\Component;

class IphonesManagements extends Component
{
    public $search = '';
    public $sortField = 'updated_at';
    public $sortDirection = 'desc';
    public $paginate = 10;

    public $mySelected = [];
    public $selectedAll = false;

    public function clicked()
    {
        $this->dispatch('showAlert', type: 'success', message: 'Data berhasil disimpan!');
    }

    public function mount()
    {
        if (session()->has('saved')) {
            LivewireAlert::title(session('saved.title'))
                ->text(session('saved.text'))
                ->success()
                ->show();
        }
    }

    public function render()
    {
        $query = $this->getData()->paginate($this->paginate);

        return view('livewire.iphones-managements', [
            'iphones' => $query,
        ]);
    }

    public function getData()
    {
        $query = Iphones::query();

        if ($this->search) {
            $query->search(['name', 'description', 'updated_at'], $this->search);
        }

        if (in_array($this->sortField, ['finish', 'pending', 'ongoing'])) {
            $query->where('status', $this->sortField);
        } else {
            $query->orderBy($this->sortField, $this->sortDirection);
        }

        // Jangan panggil get() di sini, biarkan query builder tetap sebagai objek query
        return $query;
    }

    public function updatedSelectedAll($val)
    {
        $val ? $this->mySelected = $this->getData()->limit($this->paginate)->pluck('id') : $this->mySelected = [];
    }

    public function updatedMySelected()
    {
        if (count($this->mySelected) === $this->paginate) {
            $this->selectedAll = true;
        } else {
            $this->selectedAll = false;
        }
    }

    public function updatedPage($page)
    {
        $this->mySelected = [];
        $this->selectedAll = false;
    }
}
