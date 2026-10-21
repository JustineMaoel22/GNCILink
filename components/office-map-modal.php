<link rel="stylesheet" href="/assets/css/office-map.css?v=3">
<dialog id="officeMapModal" class="office-map-modal" aria-labelledby="officeMapTitle" aria-describedby="officeMapAddress">
    <div class="office-map-panel">
        <header class="office-map-header">
            <div><span class="office-map-eyebrow">GNCI Campus · Office location</span>
                <h2 id="officeMapTitle"></h2>
                <p id="officeMapAddress"></p>
            </div>
            <button type="button" class="office-map-close" aria-label="Close campus map" autofocus><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true" focusable="false"><path d="m6 6 12 12M18 6 6 18"/></svg></button>
        </header>
        <p class="office-map-help" role="status"></p>
        <iframe class="office-map-frame" title="Interactive GNCI campus map"></iframe>
        <footer class="office-map-footer">The marker identifies the building. Floor and room details are listed above.</footer>
    </div>
</dialog>
<script src="/assets/js/office-map.js?v=3" defer></script>
