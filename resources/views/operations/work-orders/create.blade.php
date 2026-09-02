@extends('layouts.app')

@section('title', 'New Work Order')
@section('page-title', 'New work order')

@section('content')
<div class="form-page">
    <form method="POST" action="{{ route('work-orders.store') }}">
        @csrf
        <x-form-panel title="Create work order" subtitle="Add a manual task for staffing, HR, contracts or finance follow-up." :back="route('work-orders.index')">
            <div class="form-grid">
                <x-form-field label="Title" name="title" type="text" :value="old('title')" :required="true" class="sm:col-span-2" placeholder="e.g. Fill 3 guards at Site X by Friday" />
                <x-form-field label="Category" name="category" type="select" :required="true">
                    @foreach ($categories as $category)
                        <option value="{{ $category->value }}" @selected(old('category', \App\Enums\WorkOrderCategory::General->value) === $category->value)>{{ $category->label() }}</option>
                    @endforeach
                </x-form-field>
                <x-form-field label="Priority" name="priority" type="select" :required="true">
                    @foreach ($priorities as $priority)
                        <option value="{{ $priority->value }}" @selected(old('priority', \App\Enums\WorkOrderPriority::Normal->value) === $priority->value)>{{ $priority->label() }}</option>
                    @endforeach
                </x-form-field>
                <x-form-field label="Assign to" name="assigned_to" type="select">
                    <option value="">Unassigned</option>
                    @foreach ($assignees as $assignee)
                        <option value="{{ $assignee->id }}" @selected((string) old('assigned_to') === (string) $assignee->id)>{{ $assignee->name }}</option>
                    @endforeach
                </x-form-field>
                <x-form-field label="Due date" name="due_at" type="date" :value="old('due_at')" />
                <x-form-field label="Region (optional)" name="region_id" type="select">
                    <option value="">All / not region-specific</option>
                    @foreach ($regions as $region)
                        <option value="{{ $region->id }}" @selected((string) old('region_id') === (string) $region->id)>{{ $region->name }}</option>
                    @endforeach
                </x-form-field>
                <x-form-field label="Description" name="description" type="textarea" :value="old('description')" class="sm:col-span-2" />
            </div>
            <x-form-actions :cancel="route('work-orders.index')" submit-label="Create task" />
        </x-form-panel>
    </form>
</div>
@endsection
