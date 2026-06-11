@props(['name' => 'partner_id', 'selected' => null, 'required' => false])

<select name="{{ $name }}" class="w-full border border-odoo-border rounded px-3 py-2 text-sm" {{ $required ? 'required' : '' }}>
    <option value="">— Pilih —</option>
    @foreach ($partnerGroups as $groupLabel => $groupPartners)
        @if ($groupPartners->isNotEmpty())
            <optgroup label="{{ $groupLabel }}">
                @foreach ($groupPartners as $partner)
                    <option value="{{ $partner->id }}" @selected(old($name, $selected) == $partner->id)>
                        {{ $partner->code }} — {{ $partner->name }}
                    </option>
                @endforeach
            </optgroup>
        @endif
    @endforeach
</select>
