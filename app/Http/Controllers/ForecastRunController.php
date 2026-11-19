<?php

namespace App\Http\Controllers;

use App\Enums\RunStatus;
use App\Http\Resources\ForecastRunResource;
use App\Models\ForecastRun;
use App\Weather\ProviderRegistry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class ForecastRunController extends Controller
{
    public function index(Request $request, ProviderRegistry $registry): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'location_id' => ['nullable', 'digits_between:1,18'],
            'provider' => ['nullable', 'string', Rule::in($registry->known())],
            'status' => ['nullable', Rule::enum(RunStatus::class)],
        ]);

        $runs = ForecastRun::query()
            ->when($filters['location_id'] ?? null, fn ($q, $id) => $q->where('location_id', $id))
            ->when($filters['provider'] ?? null, fn ($q, $provider) => $q->where('provider', $provider))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->orderByDesc('issue_date')
            ->orderBy('provider')
            ->paginate(50)
            ->withQueryString();

        return ForecastRunResource::collection($runs);
    }
}
