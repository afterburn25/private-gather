@php
    $hostsText = old('hosts_text', collect($event->hosts ?? [])->map(fn($row) => trim(($row['name'] ?? '').'|'.($row['role'] ?? ''), '|'))->implode("\n"));
    $galleryText = old('gallery_urls', collect($event->gallery ?? [])->implode("\n"));
    $faqText = old('faq_text', collect($event->faq ?? [])->map(fn($row) => trim(($row['question'] ?? '').'|'.($row['answer'] ?? ''), '|'))->implode("\n"));
    $updatesText = old('updates_text', collect($event->updates ?? [])->map(fn($row) => trim(($row['title'] ?? '').'|'.($row['body'] ?? ''), '|'))->implode("\n"));
    $scheduleText = old('schedule_text', collect($event->schedule ?? [])->map(fn($row) => trim(($row['time'] ?? '').'|'.($row['label'] ?? ''), '|'))->implode("\n"));
@endphp

<section class="event-experience-editor" aria-labelledby="experience-heading">
    <div class="panel-head">
        <div>
            <div class="eyebrow">EXPERIENCE DESIGN</div>
            <h2 id="experience-heading">Make the event page feel complete</h2>
            <p class="muted">Hosts, schedule, gallery, FAQ and updates render as structured sections on the public/member event page. Exact map coordinates are retained only when the address is intentionally public.</p>
        </div>
    </div>

    <div class="form-grid">
        <label>
            Public latitude
            <input name="latitude" type="number" step="0.0000001" min="-90" max="90" value="{{ old('latitude', $event->latitude) }}" placeholder="32.7767">
            <small>Optional. Cleared automatically unless address visibility is Public.</small>
        </label>
        <label>
            Public longitude
            <input name="longitude" type="number" step="0.0000001" min="-180" max="180" value="{{ old('longitude', $event->longitude) }}" placeholder="-96.7970">
            <small>Use only for a deliberately public venue/location.</small>
        </label>

        <label class="span2">
            Host team
            <textarea name="hosts_text" rows="4" placeholder="Alex & Jordan|Hosts&#10;Velvet Crew|Door & hospitality">{{ $hostsText }}</textarea>
            <small>One host per line: <strong>Name | Role</strong>.</small>
        </label>

        <label class="span2">
            Run of show
            <textarea name="schedule_text" rows="5" placeholder="8:00 PM|Doors open&#10;9:00 PM|Welcome & introductions&#10;10:00 PM|Main social">{{ $scheduleText }}</textarea>
            <small>One item per line: <strong>Time | Label</strong>. Keep exact private-location instructions out of this field.</small>
        </label>

        <label class="span2">
            Gallery images
            <textarea name="gallery_urls" rows="5" placeholder="/uploads/events/lounge-1.webp&#10;https://cdn.example.com/lounge-2.webp">{{ $galleryText }}</textarea>
            <small>One HTTPS URL or local public path per line. Up to 30 images.</small>
        </label>

        <label class="span2">
            Frequently asked questions
            <textarea name="faq_text" rows="6" placeholder="Is parking available?|Yes, use the public garage on Main Street.&#10;Can I bring a guest?|Use the guest allowance shown during RSVP.">{{ $faqText }}</textarea>
            <small>One item per line: <strong>Question | Answer</strong>.</small>
        </label>

        <label class="span2">
            Event updates
            <textarea name="updates_text" rows="6" placeholder="Parking update|The north garage is now the recommended entrance.&#10;Schedule update|Doors now open at 7:30 PM.">{{ $updatesText }}</textarea>
            <small>One update per line: <strong>Title | Message</strong>. Use this for safe attendee-facing announcements, not private credentials or door codes.</small>
        </label>
    </div>
</section>
