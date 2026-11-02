        <div id="advisor-app"
            class="stage-chat"
            data-inline="true"
            data-label-connection-error="{{ $garage['connection_error'] }}"
            data-workshop-slug="{{ $workshop->slug }}"
            data-locale="{{ $locale }}"
            data-start-url="{{ route('advisor.start', $workshop->slug) }}"
            data-message-url="{{ route('advisor.message', $workshop->slug) }}"
            data-history-url="{{ route('advisor.history', $workshop->slug) }}"
            data-feedback-url="{{ route('advisor.feedback', $workshop->slug) }}"
            data-storage-key="advisor_token_{{ $workshop->slug }}"
            data-label-placeholder="{{ $labels['chat_placeholder'] }}"
            data-label-send="{{ $labels['chat_send'] }}"
            data-label-open="{{ $labels['chat_open'] }}"
            data-label-close="{{ $labels['chat_close'] }}"
            data-label-intro="{{ $labels['chat_intro'] }}"
            data-label-warranty-included="{{ $labels['warranty_included'] }}"
            data-label-service-only="{{ $labels['chat_service_only'] }}"
            data-label-day="{{ $labels['chat_day'] }}"
            data-label-no-availability="{{ $labels['chat_no_availability'] }}"
            data-label-booking-received="{{ $labels['chat_booking_received'] }}"
            data-label-reference="{{ $labels['chat_reference'] }}"
            data-label-thinking="{{ $labels['thinking'] }}"
            data-label-handed-over="{{ $labels['handed_over'] }}"
            data-label-status-sent="{{ $labels['chat_status_sent'] }}"
            data-label-status-waiting="{{ $labels['chat_status_waiting'] }}"
            data-label-status-replied="{{ $labels['chat_status_replied'] }}"
            data-label-draft-title="{{ $labels['chat_draft_title'] }}"
            data-label-draft-body="{{ $labels['chat_draft_body'] }}"
            data-label-draft-keep="{{ $labels['chat_draft_keep'] }}"
            data-label-draft-discard="{{ $labels['chat_draft_discard'] }}"
            data-label-view-details="{{ $labels['view_details'] }}"
            data-label-service-details-question="{{ $labels['service_details_question'] }}"
            data-label-book-now="{{ $labels['book_now'] }}"
            data-label-menu-heading="{{ $labels['menu_heading'] }}"
            data-label-staff="{{ $labels['menu_staff'] }}"
            data-label-error="{{ $labels['chat_error'] }}"
            data-label-slow="{{ $labels['chat_slow'] }}"
            data-label-retry="{{ $labels['chat_retry'] }}"
            data-label-mic="{{ $labels['chat_mic'] }}"
            data-label-mic-listening="{{ $labels['chat_mic_listening'] }}"
            data-label-mic-denied="{{ $labels['chat_mic_denied'] }}"
            data-label-mic-nospeech="{{ $labels['chat_mic_nospeech'] }}"
            data-label-hide-details="{{ $labels['chat_hide_details'] }}"
            data-label-form-title="{{ $labels['chat_form_title'] }}"
            data-label-form-confirm="{{ $labels['chat_form_confirm'] }}"
            data-label-from="{{ $labels['from'] }}"
            data-label-showing-services="{{ $labels['chat_showing_services'] }}"
            data-label-showing-facilities="{{ $labels['chat_showing_facilities'] }}"
            data-label-open-map="{{ $labels['chat_open_map'] }}"
            data-label-directions="{{ $labels['chat_directions'] }}"
            data-label-getting-there="{{ $labels['chat_getting_there'] }}"
            data-label-showing-photos="{{ $labels['chat_showing_photos'] }}"
            data-label-hours="{{ $labels['chat_hours'] }}"
            data-label-photo-prev="{{ $labels['chat_photo_prev'] }}"
            data-label-photo-next="{{ $labels['chat_photo_next'] }}"
            data-label-photo-close="{{ $labels['chat_photo_close'] }}"
            data-label-close-showcase="{{ $labels['chat_close_showcase'] }}"
            data-label-chat-new="{{ $labels['chat_new'] }}"
            data-label-chat-new-title="{{ $labels['chat_new_title'] }}"
            data-label-chat-new-body="{{ $labels['chat_new_body'] }}"
            data-label-chat-new-confirm="{{ $labels['chat_new_confirm'] }}"
            data-label-chat-new-keep="{{ $labels['chat_new_keep'] }}"
            data-label-price-suffix="{{ $labels['price_suffix'] }}"
            data-workshop-name="{{ $workshop->name }}"
            data-label-picker-title="{{ $labels['picker_title'] }}"
            data-label-picker-message="{{ $labels['picker_message'] }}"
            data-label-pick-start="{{ $labels['picker_pick_start'] }}"
            data-label-pick-end="{{ $labels['picker_pick_end'] }}"
            data-label-picker-continue="{{ $labels['picker_continue'] }}"
            data-label-tour-progress="{{ $labels['tour_progress'] }}"
            data-label-tour-next="{{ $labels['tour_next'] }}"
            data-label-tour-stop="{{ $labels['tour_stop'] }}"
            data-label-tour-done="{{ $labels['tour_done'] }}"
            data-label-add-calendar="{{ $labels['chat_add_calendar'] }}"
            data-label-share="{{ $labels['chat_share'] }}"
            data-label-share-text="{{ $labels['chat_share_text'] }}"
            data-today="{{ $today }}"
            data-max-days="{{ \App\Services\Reservation\ReservationService::MAX_DAYS }}"
            data-quote-url="{{ route('reservation.quote', $workshop->slug) }}"
            data-reserve-url="{{ route('reservation.store', $workshop->slug) }}"
            data-currency="{{ $workshop->currency }}"
            data-member-name="{{ $prefill['name'] ?? '' }}"
            data-member-phone="{{ $prefill['phone'] ?? '' }}"
            data-garage-url="{{ route('workshop.show', ['workshopSlug' => $workshop->slug, 'lang' => $locale]) }}"
            data-service-url="{{ route('workshop.service', ['workshopSlug' => $workshop->slug, 'serviceSlug' => '__SLUG__', 'lang' => $locale]) }}"
            data-facility-url="{{ route('workshop.facility', ['workshopSlug' => $workshop->slug, 'facilityId' => '__ID__', 'lang' => $locale]) }}"
            data-staff-url="{{ route('workshop.staff', ['workshopSlug' => $workshop->slug, 'lang' => $locale]) }}"
        >
            <script type="application/json" data-wizard-labels>@json($wizard)</script>
            <script type="application/json" data-service-terms>@json($serviceTerms)</script>
            @php
                // Tappable answers the advisor offers under a reply; a key without a message is handled by the page itself.
                $quickReplies = [
                    'pick_dates' => ['label' => $labels['quick_pick_dates']],
                    'tour' => ['label' => $labels['quick_tour']],
                    'cheapest' => ['label' => $labels['quick_cheapest'], 'message' => $labels['quick_cheapest_q']],
                    'family' => ['label' => $labels['quick_family'], 'message' => $labels['quick_family_q']],
                    'view' => ['label' => $labels['quick_view'], 'message' => $labels['quick_view_q']],
                    'services' => ['label' => $labels['menu_services'], 'message' => $labels['menu_services_q']],
                    'facilities' => ['label' => $labels['menu_facilities'], 'message' => $labels['quick_facilities_q']],
                    'location' => ['label' => $labels['quick_location'], 'message' => $labels['quick_location_q']],
                    'menu' => ['label' => $labels['quick_menu'], 'message' => $labels['quick_menu_q']],
                    'policies' => ['label' => $labels['menu_policies'], 'message' => $labels['menu_policies_q']],
                    'staff' => ['label' => $labels['menu_staff'], 'message' => $labels['menu_staff_q']],
                ];
            @endphp
            <script type="application/json" data-quick-replies>@json($quickReplies)</script>
            <script type="application/json" data-tour-stops>@json($tourStops)</script>

            <section id="advisor-chat-log" class="chat-log" aria-label="{{ $labels['chat_heading'] }}" aria-hidden="true">
                <header class="chat-log-header">
                    <span class="chat-host-avatar" aria-hidden="true"></span>
                    <div class="min-w-0 flex-1">
                        <p class="chat-title flex items-center gap-1.5 font-semibold">
                            {{ $labels['chat_heading'] }}
                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                        </p>
                        <p class="chat-subtitle truncate text-xs">{{ $labels['chat_subtitle'] }}</p>
                        <p data-chat-status class="chat-status" aria-live="polite" hidden></p>
                    </div>
                    <button type="button" data-chat-new class="chat-log-close" aria-label="{{ $labels['chat_new'] }}" title="{{ $labels['chat_new'] }}">
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 12a8 8 0 1 1-2.34-5.66M20 4v5h-5"/></svg>
                    </button>
                    <button type="button" data-chat-close class="chat-log-close" aria-label="{{ $labels['chat_close'] }}"><span aria-hidden="true">×</span></button>
                </header>

                <div data-messages role="log" aria-live="polite" aria-label="{{ $labels['chat_heading'] }}" class="chat-log-messages space-y-3"></div>

                <div data-thinking-indicator class="chat-thinking" role="status" hidden>
                    <span class="chat-host-avatar" aria-hidden="true"></span>
                    <span class="chat-thinking-bubble">
                        <span class="chat-thinking-dots" aria-hidden="true"><i></i><i></i><i></i></span>
                        <span>{{ $labels['thinking'] }}</span>
                    </span>
                </div>

                <div data-status-banner class="hidden border-t border-rose-200 bg-rose-50 px-4 py-2 text-xs text-rose-800"></div>
            </section>

            <form data-chat-form data-chat-composer class="chat-bar" aria-hidden="true">
                <input
                    type="text"
                    data-chat-input
                    aria-label="{{ $labels['chat_placeholder'] }}"
                    maxlength="4000"
                    placeholder="{{ $labels['chat_placeholder'] }}"
                    autocomplete="off"
                >
                <button type="button" data-chat-mic class="chat-mic" aria-label="{{ $labels['chat_mic'] }}" aria-pressed="false" title="{{ $labels['chat_mic'] }}" hidden>
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="9" y="3" width="6" height="12" rx="3"/><path d="M5 11a7 7 0 0 0 14 0M12 18v3"/></svg>
                </button>
                <button type="submit" data-chat-submit>{{ $labels['chat_send'] }}</button>
            </form>

            <div data-draft-dialog class="chat-draft-dialog" hidden aria-hidden="true" role="dialog" aria-modal="false" aria-labelledby="chat-draft-title">
                <p id="chat-draft-title" data-draft-title class="font-semibold text-stone-900">{{ $labels['chat_draft_title'] }}</p>
                <p data-draft-body class="mt-1 text-xs leading-relaxed text-stone-600">{{ $labels['chat_draft_body'] }}</p>
                <div class="mt-3 flex justify-end gap-2">
                    <button type="button" data-draft-keep class="rounded-full border border-stone-300 bg-white px-3 py-1.5 text-xs font-medium text-stone-700">{{ $labels['chat_draft_keep'] }}</button>
                    <button type="button" data-draft-discard class="rounded-full bg-stone-900 px-3 py-1.5 text-xs font-medium text-white">{{ $labels['chat_draft_discard'] }}</button>
                </div>
            </div>

            <div data-reset-dialog class="chat-draft-dialog" hidden aria-hidden="true" role="dialog" aria-modal="false" aria-labelledby="chat-reset-title">
                <p id="chat-reset-title" class="font-semibold text-stone-900">{{ $labels['chat_new_title'] }}</p>
                <p class="mt-1 text-xs leading-relaxed text-stone-600">{{ $labels['chat_new_body'] }}</p>
                <div class="mt-3 flex justify-end gap-2">
                    <button type="button" data-reset-keep class="rounded-full border border-stone-300 bg-white px-3 py-1.5 text-xs font-medium text-stone-700">{{ $labels['chat_new_keep'] }}</button>
                    <button type="button" data-reset-confirm class="rounded-full bg-stone-900 px-3 py-1.5 text-xs font-medium text-white">{{ $labels['chat_new_confirm'] }}</button>
                </div>
            </div>

            <button type="button" data-chat-launcher class="chat-launcher" aria-controls="advisor-chat-log" aria-expanded="false">
                <span class="chat-launcher-dot" aria-hidden="true"></span>
                <span>{{ $labels['chat_open'] }}</span>
                <span class="chat-launcher-arrow" aria-hidden="true">↗</span>
            </button>

            {{-- Each chip walks the customer to its scene; the advisor then carries on with that topic. --}}
            @php
                $chipLabels = ['services' => $labels['menu_services'], 'facilities' => $labels['menu_facilities'], 'info' => $labels['menu_policies'], 'reservation' => $garage['start_booking'], 'staff' => $labels['menu_staff']];
            @endphp
            <ul class="chat-chips" aria-label="{{ $labels['menu_heading'] }}">
                @foreach ($menuItems as $item)
                    <li><a href="{{ $item['href'] }}" data-stage-exit data-topic="{{ $item['topic'] }}" @if($item['tour']) data-tour-line="{{ $item['tour'] }}" @endif>{{ $chipLabels[$item['key']] ?? $item['label'] }} <span aria-hidden="true">›</span></a></li>
                @endforeach
                <li><button type="button" data-tour-start><span aria-hidden="true">✦</span> {{ $labels['quick_tour'] }}</button></li>
            </ul>
        </div>
