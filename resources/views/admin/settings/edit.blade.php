<x-admin-layout title="Workshop settings">
    @if ($errors->any())
        <ul class="mb-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
            @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    @endif

    <form method="POST" action="{{ route('admin.settings.update') }}" enctype="multipart/form-data" class="space-y-6 text-sm">
        @csrf @method('PUT')

        @php
            $field = 'mt-1 w-full rounded-lg border border-stone-300 px-3 py-2';
        @endphp

        <section class="rounded-xl border border-stone-200 bg-white p-4">
            <h2 class="font-medium text-stone-900">Profile</h2>
            <div class="mt-3 grid gap-3 sm:grid-cols-2">
                <label class="block sm:col-span-2">Workshop name<input name="name" value="{{ old('name', $workshop->name) }}" required class="{{ $field }}"></label>
                <label class="block sm:col-span-2">About the workshop<textarea name="description" rows="3" class="{{ $field }}">{{ old('description', $workshop->description) }}</textarea></label>
                <label class="block sm:col-span-2">Address<input name="address" value="{{ old('address', $workshop->address) }}" class="{{ $field }}"></label>
                <label class="block">City<input name="city" value="{{ old('city', $workshop->city) }}" class="{{ $field }}"></label>
                <label class="block">Country<input name="country" value="{{ old('country', $workshop->country) }}" class="{{ $field }}"></label>
                <label class="block">Latitude<input name="latitude" value="{{ old('latitude', $workshop->latitude) }}" class="{{ $field }}" placeholder="-8.409518"></label>
                <label class="block">Longitude<input name="longitude" value="{{ old('longitude', $workshop->longitude) }}" class="{{ $field }}" placeholder="115.188919"></label>
            </div>
        </section>

        <section class="rounded-xl border border-stone-200 bg-white p-4">
            <h2 class="font-medium text-stone-900">Contact</h2>
            <p class="text-xs text-stone-400">Customers reach the team through these. The email also receives replies to booking notifications.</p>
            <div class="mt-3 grid gap-3 sm:grid-cols-3">
                <label class="block">Phone<input name="phone" value="{{ old('phone', $workshop->phone) }}" class="{{ $field }}"></label>
                <label class="block">WhatsApp<input name="whatsapp" value="{{ old('whatsapp', $workshop->whatsapp) }}" class="{{ $field }}" placeholder="+62 …"></label>
                <label class="block">Email<input type="email" name="email" value="{{ old('email', $workshop->email) }}" class="{{ $field }}"></label>
            </div>
        </section>

        <section class="rounded-xl border border-stone-200 bg-white p-4">
            <h2 class="font-medium text-stone-900">Visit rules</h2>
            <div class="mt-3 grid gap-3 sm:grid-cols-3">
                <label class="block">Drop-off from<input type="time" name="opening_time" value="{{ old('opening_time', substr((string) $workshop->opening_time, 0, 5)) }}" required class="{{ $field }}"></label>
                <label class="block">Pickup until<input type="time" name="closing_time" value="{{ old('closing_time', substr((string) $workshop->closing_time, 0, 5)) }}" required class="{{ $field }}"></label>
                <label class="block">Currency<input name="currency" value="{{ old('currency', $workshop->currency) }}" maxlength="3" required class="{{ $field }} uppercase"></label>
                <label class="block sm:col-span-2">Time zone
                    <select name="timezone" class="{{ $field }}">
                        @foreach (timezone_identifiers_list() as $zone)<option value="{{ $zone }}" @selected(old('timezone', $workshop->timezone) === $zone)>{{ $zone }}</option>@endforeach
                    </select>
                </label>
                <label class="block">Main language
                    <select name="default_locale" class="{{ $field }}">
                        @foreach (['id' => 'Indonesian', 'en' => 'English', 'ja' => 'Japanese'] as $code => $name)<option value="{{ $code }}" @selected(old('default_locale', $workshop->default_locale) === $code)>{{ $name }}</option>@endforeach
                    </select>
                </label>
            </div>
        </section>

        <section class="rounded-xl border border-stone-200 bg-white p-4">
            <h2 class="font-medium text-stone-900">Look</h2>
            <div class="mt-3 grid gap-4 sm:grid-cols-2">
                <div>
                    <p>Logo</p>
                    @if ($workshop->logoUrl())<img src="{{ $workshop->logoUrl() }}" alt="" class="mt-2 h-16 rounded-lg border border-stone-200 bg-stone-100 p-1"><label class="mt-1 flex items-center gap-1 text-xs text-stone-500"><input type="checkbox" name="remove_logo" value="1"> Remove the logo</label>@endif
                    <input type="file" name="logo" accept="image/jpeg,image/png,image/webp" class="mt-2 block w-full text-sm">
                    <p class="mt-1 text-xs text-stone-400">Shown in the workshop header instead of the first letter. A square or wide PNG with a transparent background works best.</p>
                </div>
                <div>
                    <p>Cover picture</p>
                    @if ($workshop->coverUrl())<img src="{{ $workshop->coverUrl() }}" alt="" class="mt-2 h-16 rounded-lg border border-stone-200 object-cover"><label class="mt-1 flex items-center gap-1 text-xs text-stone-500"><input type="checkbox" name="remove_cover" value="1"> Remove the cover</label>@endif
                    <input type="file" name="cover" accept="image/jpeg,image/png,image/webp" class="mt-2 block w-full text-sm">
                    <p class="mt-1 text-xs text-stone-400">Used when the workshop link is shared in chats and on social media.</p>
                </div>
            </div>
        </section>

        <section class="rounded-xl border border-stone-200 bg-white p-4">
            <h2 class="font-medium text-stone-900">Address on the web</h2>
            <p class="text-xs text-stone-400">Customers currently open the workshop at {{ url($workshop->slug) }}.</p>
            <label class="mt-3 block">Own domain (optional)<input name="custom_domain" value="{{ old('custom_domain', $workshop->custom_domain) }}" placeholder="workshop.example.com" class="{{ $field }}"></label>
            <p class="mt-1 text-xs text-stone-400">Point the domain at this site with your hosting provider first; saving it here only tells the app which workshop the domain belongs to.</p>
        </section>

        <section class="rounded-xl border border-stone-200 bg-white p-4">
            <h2 class="font-medium text-stone-900">AI Advisor</h2>
            <label class="mt-3 block">Guidance for the advisor (optional)
                <textarea name="ai_instructions" rows="4" maxlength="1500" class="{{ $field }}" placeholder="e.g. Speak warmly and clearly. Mention that the waiting lounge has free coffee. Never promise a repair date; ask customers to contact the service desk.">{{ old('ai_instructions', $workshop->setting('ai_instructions')) }}</textarea>
            </label>
            <p class="mt-1 text-xs text-stone-400">Added to the advisor's instructions. It cannot override the built-in rules: prices come only from the system, and the advisor visits on the topic of this workshop.</p>
        </section>

        <section class="rounded-xl border border-stone-200 bg-white p-4">
            <h2 class="font-medium text-stone-900">Visibility</h2>
            <select name="public_status" class="{{ $field }} sm:w-64">
                <option value="draft" @selected(old('public_status', $workshop->public_status) === 'draft')>Draft — hidden from customers</option>
                <option value="published" @selected(old('public_status', $workshop->public_status) === 'published')>Published — customers can visit</option>
            </select>
        </section>

        <button type="submit" class="rounded-lg bg-stone-900 px-5 py-2.5 font-medium text-white hover:bg-stone-700">Save settings</button>
    </form>
</x-admin-layout>
