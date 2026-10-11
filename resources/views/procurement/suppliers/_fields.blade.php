<x-form-field label="Name" name="name" :value="old('name', $supplier->name ?? '')" required />
<x-form-field label="Contact person" name="contact_person" :value="old('contact_person', $supplier->contact_person ?? '')" />
<x-form-field label="Phone" name="phone" :value="old('phone', $supplier->phone ?? '')" />
<x-form-field label="Email" name="email" type="email" :value="old('email', $supplier->email ?? '')" />
<x-form-field label="TIN" name="tin" :value="old('tin', $supplier->tin ?? '')" />
<x-form-field label="Category" name="category" type="select" required>
    @foreach ($categories as $category)
        <option value="{{ $category->value }}" @selected(old('category', $supplier->category->value ?? '') === $category->value)>{{ $category->label() }}</option>
    @endforeach
</x-form-field>
<x-form-field label="Physical address" name="address" :value="old('address', $supplier->address ?? '')" />
<x-form-field label="Delivery address" name="delivery_address" :value="old('delivery_address', $supplier->delivery_address ?? '')" />
<x-form-field label="Notes" name="notes" type="textarea" :value="old('notes', $supplier->notes ?? '')" />
<x-form-field label="Supporting document" name="document" type="file" />
<label class="inline-flex items-center gap-2 pb-1 text-[11px] text-slate-700 dark:text-slate-300">
    <input type="checkbox" name="is_active" value="1" class="rounded border-slate-300" @checked(old('is_active', $supplier->is_active ?? true))>
    Active supplier
</label>
