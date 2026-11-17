<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Venue;
use App\Services\CategoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

final class CategoryController extends Controller
{
    public function __construct(private CategoryService $categories)
    {
    }

    public function propose(): View
    {
        return view('categories.form', $this->formData(null));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->categories->createProposal($this->validated($request), $request->user()->id);

        return redirect()
            ->route('proposals.index')
            ->with('status', 'Návrh kategorie byl odeslán ke schválení.');
    }

    public function editProposal(Category $category): View
    {
        Gate::authorize('updateProposal', $category);

        return view('categories.form', $this->formData($category));
    }

    public function updateProposal(Request $request, Category $category): RedirectResponse
    {
        Gate::authorize('updateProposal', $category);

        $this->categories->updateProposal($category, $this->validated($request));

        return redirect()
            ->route('proposals.index')
            ->with('status', 'Návrh kategorie byl upraven a čeká na schválení.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(?Category $category): array
    {
        return [
            'category' => $category,
            'name' => old('name', $category?->name ?? ''),
            'description' => old('description', $category?->description ?? ''),
            'parentId' => old('parent_id', $category?->parent_id),
            'options' => $this->categories->approvedOptions($category),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = Validator::make($request->all(), [
            'name' => ['required', 'string', 'min:2', 'max:80'],
            'description' => ['nullable', 'string', 'max:2000'],
            'parent_id' => ['nullable', 'integer'],
        ], [], [
            'name' => 'název kategorie',
            'description' => 'popis',
            'parent_id' => 'rodičovská kategorie',
        ])->validate();

        $data['parent_id'] = $data['parent_id'] === null ? null : (int) $data['parent_id'];

        return $data;
    }
}
