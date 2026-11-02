<section class="garage-content stage-panel-right @container" aria-label="{{ $wizard['title'] }}">
    <a href="{{ route('workshop.show', ['workshopSlug' => $workshop->slug, 'lang' => $locale]) }}" data-stage-exit data-tour-line="{{ $narration['tour_garage'] }}" class="panel-close" aria-label="{{ $garage['back'] }}"><span aria-hidden="true">×</span></a>
    <p class="garage-eyebrow">{{ $workshop->name }}</p>
    <h2>{{ $wizard['title'] }}</h2>

    <div class="wizard" data-wizard
        data-quote-url="{{ route('reservation.quote', $workshop->slug) }}"
        data-submit-url="{{ route('reservation.store', $workshop->slug) }}"
        data-workshop-slug="{{ $workshop->slug }}"
        data-locale="{{ $locale }}"
        data-currency="{{ $workshop->currency }}"
        data-today="{{ $today }}"
        data-max-days="{{ \App\Services\Reservation\ReservationService::MAX_DAYS }}"
        data-preselect-service="{{ $preselectedService ?? '' }}"
    >
        <div data-wizard-flow>
            <p class="mt-3 max-w-xl text-sm leading-relaxed text-stone-600">{{ $wizard['intro'] }}</p>

            <div class="wizard-progress" aria-label="{{ $wizard['title'] }}">
                <p class="wizard-step-label" data-wizard-step-label aria-live="polite"></p>
                <ol>
                    @foreach ($wizard['steps'] as $stepLabel)
                        <li data-progress-step><span>{{ $stepLabel }}</span></li>
                    @endforeach
                </ol>
            </div>

            <form data-wizard-form novalidate autocomplete="on">
                <div data-step="1" class="wizard-step">
                    <label class="wizard-field">
                        <span>{{ $wizard['drop_off_date'] }}</span>
                        <input type="date" name="drop_off_date" min="{{ $today }}" required>
                    </label>
                    <label class="wizard-field">
                        <span>{{ $wizard['pickup_date'] }}</span>
                        <input type="date" name="pickup_date" min="{{ $today }}">
                    </label>
                    <p class="wizard-hint" data-days-hint aria-live="polite"></p>
                </div>

                <div data-step="2" class="wizard-step" hidden>
                    <label class="wizard-field wizard-field-wide">
                        <span>{{ $wizard['vehicle_model'] }}</span>
                        <input type="text" name="vehicle_model" maxlength="100" placeholder="{{ $wizard['vehicle_model_placeholder'] }}" required>
                    </label>
                    <label class="wizard-field">
                        <span>{{ $wizard['license_plate'] }}</span>
                        <input type="text" name="license_plate" maxlength="20" placeholder="B 1234 XYZ" required>
                    </label>
                    <label class="wizard-field">
                        <span>{{ $wizard['vehicles'] }}</span>
                        <input type="number" name="vehicles" min="1" max="10" value="1" inputmode="numeric" required>
                    </label>
                </div>

                <div data-step="3" class="wizard-step wizard-step-wide" hidden>
                    <fieldset>
                        <legend>{{ $wizard['choose_service'] }}</legend>
                        <div class="wizard-services">
                            @foreach ($serviceTypes as $serviceType)
                                <label class="wizard-service" data-service-option
                                    data-name="{{ $serviceType->translatedName($locale) }}"
                                    data-towing="{{ $serviceType->towing_available ? 1 : 0 }}"
                                >
                                    <input type="radio" name="service_type_slug" value="{{ $serviceType->slug }}">
                                    <span class="wizard-service-body">
                                        <strong>{{ $serviceType->translatedName($locale) }}</strong>
                                        @if ($serviceType->duration_minutes)<span>{{ str_replace(':count', (string) $serviceType->duration_minutes, $wizard['fits']) }}</span>@endif
                                        <span class="wizard-service-price">{{ $labels['from'] }} {{ $workshop->money($serviceType->base_price) }}</span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                    <label class="wizard-check" data-towing-field hidden>
                        <input type="checkbox" name="towing" value="1">
                        <span>{{ $wizard['towing'] }}</span>
                    </label>
                </div>

                <div data-step="4" class="wizard-step" hidden>
                    <label class="wizard-field wizard-field-wide">
                        <span>{{ $wizard['name'] }}</span>
                        <input type="text" name="customer_name" maxlength="100" autocomplete="name" value="{{ $prefill['name'] ?? '' }}" required>
                    </label>
                    <fieldset class="wizard-field-wide">
                        <legend>{{ $wizard['contact_method'] }}</legend>
                        <div class="wizard-pills">
                            @foreach (['whatsapp', 'phone', 'email'] as $method)
                                <label><input type="radio" name="contact_type" value="{{ $method }}" @checked($method === ($prefill['contact_type'] ?? 'whatsapp'))><span>{{ $wizard[$method] }}</span></label>
                            @endforeach
                        </div>
                    </fieldset>
                    <label class="wizard-field wizard-field-wide">
                        <span>{{ $wizard['contact_value'] }}</span>
                        <input type="text" name="contact_value" maxlength="120" autocomplete="tel" value="{{ $prefill['contact_value'] ?? '' }}" required>
                    </label>
                    <label class="wizard-field wizard-field-wide">
                        <span>{{ $wizard['special'] }}</span>
                        <textarea name="special_request" rows="2" maxlength="500" placeholder="{{ $wizard['special_placeholder'] }}"></textarea>
                    </label>
                </div>

                <div data-step="5" class="wizard-step wizard-step-wide" hidden>
                    <dl class="wizard-summary" data-summary></dl>
                    <p class="wizard-total"><span>{{ $wizard['estimated_total'] }}</span> <strong data-summary-total></strong></p>
                    <p class="wizard-hint">{{ $wizard['disclaimer'] }}</p>
                </div>

                <div class="wizard-error" data-wizard-error role="alert" hidden>
                    <p data-wizard-error-text></p>
                    <div class="wizard-alternatives" data-wizard-alternatives hidden>
                        <p>{{ $wizard['available_instead'] }}</p>
                        <ul></ul>
                    </div>
                </div>

                <div class="wizard-actions">
                    <button type="button" class="wizard-secondary" data-wizard-back hidden>{{ $wizard['back'] }}</button>
                    <button type="button" class="garage-action" data-wizard-next>{{ $wizard['next'] }} <span aria-hidden="true">→</span></button>
                    <button type="submit" class="garage-action" data-wizard-submit hidden>{{ $wizard['submit'] }}</button>
                </div>
            </form>
        </div>

        <div class="wizard-done" data-wizard-done hidden>
            <p class="garage-eyebrow">{{ $wizard['done_title'] }}</p>
            <p class="wizard-reference-label">{{ $wizard['reference'] }}</p>
            <p class="wizard-reference" data-done-reference></p>
            <p class="wizard-status">{{ $wizard['awaiting'] }}</p>
            <p class="mt-4 max-w-md text-sm leading-relaxed text-stone-600">{{ $wizard['done_hint'] }}</p>
            <div class="mt-6 flex flex-wrap gap-3">
                <a class="garage-action" data-done-whatsapp target="_blank" rel="noopener" hidden>{{ $wizard['send_whatsapp'] }} <span aria-hidden="true">↗</span></a>
                <a class="wizard-secondary" data-done-phone hidden>{{ $wizard['call_workshop'] }}</a>
                <a class="wizard-secondary" data-done-email hidden>{{ $wizard['email_workshop'] }}</a>
            </div>
            <div class="mt-6 flex flex-wrap gap-3">
                <a href="{{ route('workshop.show', ['workshopSlug' => $workshop->slug, 'lang' => $locale]) }}" data-stage-exit class="wizard-secondary">{{ $garage['back'] }}</a>
                <button type="button" class="wizard-secondary" data-wizard-reset>{{ $wizard['new_request'] }}</button>
            </div>
        </div>

        <script type="application/json" data-wizard-labels>@json($wizard)</script>
    </div>
</section>
