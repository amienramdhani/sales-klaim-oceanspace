<?php
$html = file_get_contents('public/test-mobile-preview.html');
$debugScript = <<<JS
<script>
window.addEventListener('DOMContentLoaded', () => {
    let out = [];
    const firstRow = document.querySelector('tr.fi-ta-row');
    if (firstRow) {
        firstRow.querySelectorAll('*').forEach(el => {
            if (el.scrollWidth > 350) {
                out.push(el.tagName + '.' + Array.from(el.classList).slice(0, 3).join('.') + ' sw=' + el.scrollWidth);
            }
        });
    }
    const pre = document.createElement('pre');
    pre.id = 'debug-result';
    pre.textContent = out.join('\\n');
    document.body.prepend(pre);
});
</script>
JS;
// Replace old script
$html = preg_replace('/<script>.*?<\/script>/s', $debugScript, $html);
file_put_contents('public/test-mobile-preview.html', $html);
