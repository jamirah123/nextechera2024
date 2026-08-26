<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Deployment;
use App\Models\Guard;
use App\Models\Region;
use App\Models\Shift;
use App\Models\Site;
use App\Models\Supervisor;
use Illuminate\Support\Collection;

class GlobalSearchService
{
    /**
     * @return list<array{type: string, label: string, title: string, subtitle: string, url: string, badge: string|null}>
     */
    public function search(string $query, int $limitPerType = 5): array
    {
        $term = trim($query);

        if (mb_strlen($term) < 2) {
            return [];
        }

        return collect()
            ->merge($this->guards($term, $limitPerType))
            ->merge($this->deployments($term, $limitPerType))
            ->merge($this->shifts($term, $limitPerType))
            ->merge($this->regions($term, $limitPerType))
            ->merge($this->supervisors($term, $limitPerType))
            ->merge($this->clients($term, $limitPerType))
            ->merge($this->sites($term, $limitPerType))
            ->take(20)
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, array{type: string, label: string, title: string, subtitle: string, url: string, badge: string|null}>
     */
    private function shifts(string $term, int $limit): Collection
    {
        return Shift::query()
            ->with(['assignedGuard:id,employment_id,full_name', 'site:id,name,code'])
            ->search($term)
            ->latest('starts_at')
            ->limit($limit)
            ->get()
            ->map(fn (Shift $shift) => [
                'type' => 'shift',
                'label' => 'Shift',
                'title' => $shift->assignedGuard?->full_name ?? $shift->reference,
                'subtitle' => $shift->reference.' · '.($shift->site?->name ?? 'No site').' · '.$shift->timeLabel(),
                'url' => route('shifts.show', $shift),
                'badge' => $shift->status->label(),
            ]);
    }

    /**
     * @return Collection<int, array{type: string, label: string, title: string, subtitle: string, url: string, badge: string|null}>
     */
    private function deployments(string $term, int $limit): Collection
    {
        return Deployment::query()
            ->with(['assignedGuard:id,employment_id,full_name', 'site:id,name,code'])
            ->current()
            ->search($term)
            ->latest('start_date')
            ->limit($limit)
            ->get()
            ->map(fn (Deployment $deployment) => [
                'type' => 'deployment',
                'label' => 'Deployment',
                'title' => $deployment->assignedGuard?->full_name ?? 'Deployment',
                'subtitle' => ($deployment->assignedGuard?->employment_id ?? '').' · '.($deployment->site?->name ?? 'No site'),
                'url' => route('deployments.show', $deployment),
                'badge' => $deployment->status->label(),
            ]);
    }

    /**
     * @return Collection<int, array{type: string, label: string, title: string, subtitle: string, url: string, badge: string|null}>
     */
    private function guards(string $term, int $limit): Collection
    {
        return Guard::query()
            ->with('region:id,name')
            ->search($term)
            ->orderBy('full_name')
            ->limit($limit)
            ->get()
            ->map(fn (Guard $guard) => [
                'type' => 'guard',
                'label' => 'Guard',
                'title' => $guard->full_name,
                'subtitle' => $guard->employment_id.' · '.($guard->region?->name ?? 'No region').' · '.$guard->operational_status->label(),
                'url' => route('guards.show', $guard),
                'badge' => $guard->employment_status->label(),
            ]);
    }

    /**
     * @return Collection<int, array{type: string, label: string, title: string, subtitle: string, url: string, badge: string|null}>
     */
    private function regions(string $term, int $limit): Collection
    {
        return Region::query()
            ->search($term)
            ->orderBy('name')
            ->limit($limit)
            ->get()
            ->map(fn (Region $region) => [
                'type' => 'region',
                'label' => 'Region',
                'title' => $region->name,
                'subtitle' => $region->code.($region->manager_name ? ' · '.$region->manager_name : ''),
                'url' => route('regions.show', $region),
                'badge' => $region->status->label(),
            ]);
    }

    /**
     * @return Collection<int, array{type: string, label: string, title: string, subtitle: string, url: string, badge: string|null}>
     */
    private function supervisors(string $term, int $limit): Collection
    {
        return Supervisor::query()
            ->with('region:id,name')
            ->search($term)
            ->orderBy('name')
            ->limit($limit)
            ->get()
            ->map(fn (Supervisor $supervisor) => [
                'type' => 'supervisor',
                'label' => 'Supervisor',
                'title' => $supervisor->name,
                'subtitle' => $supervisor->supervisor_code.' · '.($supervisor->region?->name ?? 'No region'),
                'url' => route('supervisors.show', $supervisor),
                'badge' => $supervisor->status->label(),
            ]);
    }

    /**
     * @return Collection<int, array{type: string, label: string, title: string, subtitle: string, url: string, badge: string|null}>
     */
    private function clients(string $term, int $limit): Collection
    {
        return Client::query()
            ->search($term)
            ->orderBy('name')
            ->limit($limit)
            ->get()
            ->map(fn (Client $client) => [
                'type' => 'client',
                'label' => 'Client',
                'title' => $client->name,
                'subtitle' => $client->code.($client->contact_person ? ' · '.$client->contact_person : ''),
                'url' => route('clients.show', $client),
                'badge' => $client->contract_status->label(),
            ]);
    }

    /**
     * @return Collection<int, array{type: string, label: string, title: string, subtitle: string, url: string, badge: string|null}>
     */
    private function sites(string $term, int $limit): Collection
    {
        $like = '%'.$term.'%';

        return Site::query()
            ->with(['client:id,name', 'region:id,name', 'supervisor:id,name'])
            ->where(function ($q) use ($like): void {
                $q->where('name', 'like', $like)
                    ->orWhere('code', 'like', $like)
                    ->orWhere('physical_location', 'like', $like)
                    ->orWhere('site_contact_person', 'like', $like)
                    ->orWhereHas('client', function ($client) use ($like): void {
                        $client->where('name', 'like', $like)->orWhere('code', 'like', $like);
                    });
            })
            ->orderBy('name')
            ->limit($limit)
            ->get()
            ->map(fn (Site $site) => [
                'type' => 'site',
                'label' => 'Site',
                'title' => $site->name,
                'subtitle' => $site->code.' · '.($site->client?->name ?? 'No client').' · '.($site->region?->name ?? 'No region'),
                'url' => route('sites.show', $site),
                'badge' => $site->status->label(),
            ]);
    }
}
