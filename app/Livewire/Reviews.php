<?php

namespace App\Livewire;

use App\Models\Review;
use Illuminate\Support\Str;
use Jantinnerezo\LivewireAlert\Facades\LivewireAlert;
use Livewire\Component;

class Reviews extends Component
{
    public $iphone_id;
    public $comment;
    public $rating;
    public $name;
    public $reviews;
    public $avgRating;

    public function mount($iphone_id, $rating, $name)
    {
        $this->iphone_id =  $iphone_id;
        $this->rating = $rating;
        $this->name = $name;
        $this->avgRating = number_format(round(Review::avg('rating') * 2) / 2, 1);
        $this->getReviews();
    }

    public function getReviews()
    {
        $this->reviews = Review::orderBy('created_at', 'desc')->get();
    }

    public function save()
    {
        $this->validate([
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
        ]);

        Review::create([
            'name' => $this->name,
            'rating' => $this->rating,
            'comment' => $this->comment,
            'iphone_id' => $this->iphone_id
        ]);

        $this->reset([
            'comment',
            'rating',
        ]);

        LivewireAlert::title('Review ditambahkan')
            ->text('Berhasil menambahkan reviews')
            ->success()
            ->toast()
            ->position('top-end')
            ->show();

        $this->getReviews();
    }

    function generateAnonymousName(): string
    {
        $prefix = 'anon';
        $code = strtoupper(Str::random(6)); // gunakan helper Laravel Str
        return "{$prefix}-{$code}";
    }


    public function render()
    {
        return view('livewire.reviews');
    }
}
