</main>
<footer class="text-center text-muted small py-3 border-top">
    HUIT Schedule Hub · Mã nguồn mở theo giấy phép MIT
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function copyLink(btn) {
    const text = btn.dataset.link;
    const done = () => { btn.innerHTML = '<i class="bi bi-check2"></i>'; };
    if (navigator.clipboard && window.isSecureContext) { navigator.clipboard.writeText(text).then(done); return; }
    const ta = document.createElement('textarea'); ta.value = text; document.body.appendChild(ta);
    ta.select(); document.execCommand('copy'); ta.remove(); done();
}
</script>
<?php foreach (($scripts ?? []) as $src): ?>
<script src="<?= h($src) ?>"></script>
<?php endforeach; ?>
</body>
</html>
