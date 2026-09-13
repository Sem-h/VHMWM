        </div>
    </main>
    
    <script>
        // Modal işlemleri
        function openModal(id) {
            document.getElementById(id).classList.add('active');
        }
        
        function closeModal(id) {
            document.getElementById(id).classList.remove('active');
        }
        
        /* Durum değiştiren istekler POST ile gider.
           Eskiden adrese gidiliyordu; giriş yapmış bir yöneticiye
           gösterilen zararlı bir sayfa, onun adına kayıt sildirebiliyordu. */
        function gonderPost(url) {
            var parca = String(url).split('?');
            var form = document.createElement('form');
            form.method = 'post';
            form.action = parca[0] || window.location.pathname;

            new URLSearchParams(parca[1] || '').forEach(function (deger, ad) {
                var alan = document.createElement('input');
                alan.type = 'hidden';
                alan.name = ad;
                alan.value = deger;
                form.appendChild(alan);
            });

            var belirtec = document.createElement('input');
            belirtec.type = 'hidden';
            belirtec.name = '_token';
            var etiket = document.querySelector('meta[name="csrf-token"]');
            belirtec.value = etiket ? etiket.content : '';
            form.appendChild(belirtec);

            document.body.appendChild(form);
            form.submit();
        }

        // Silme onayı
        function confirmDelete(message, url) {
            if (confirm(message || 'Bu kaydı silmek istediğinizden emin misiniz?')) {
                gonderPost(url);
            }
        }
    </script>
</body>
</html>

