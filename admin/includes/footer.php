            </main>
        </div><!-- /.y-govde -->
    </div><!-- /.yonetim -->

    <script>
        (function () {
            /* ---------- Bölümleri aç/kapa ----------
               Yalnızca açık sayfanın bölümü açık başlar; kullanıcının
               açtığı bölümler tarayıcıda hatırlanır. */
            var menu = document.getElementById('y-menu');
            var ANAHTAR = 'vhm_panel_acik_bolumler';

            function okunanBolumler() {
                try {
                    return JSON.parse(localStorage.getItem(ANAHTAR) || '[]');
                } catch (e) {
                    return [];
                }
            }

            function yazBolumler(liste) {
                try {
                    localStorage.setItem(ANAHTAR, JSON.stringify(liste));
                } catch (e) {
                    /* gizli pencerede depolama kapalı olabilir */
                }
            }

            if (menu) {
                okunanBolumler().forEach(function (ad) {
                    var b = menu.querySelector('.y-bolum[data-bolum="' + ad + '"]');
                    if (b) {
                        b.classList.add('acik');
                        var d = b.querySelector('.y-bolum-bas');
                        if (d) { d.setAttribute('aria-expanded', 'true'); }
                    }
                });

                menu.addEventListener('click', function (olay) {
                    var dugme = olay.target.closest('.y-bolum-bas');
                    if (!dugme) { return; }

                    var bolum = dugme.closest('.y-bolum');
                    var acik = bolum.classList.toggle('acik');
                    dugme.setAttribute('aria-expanded', acik ? 'true' : 'false');

                    var ad = bolum.dataset.bolum;
                    var liste = okunanBolumler().filter(function (x) { return x !== ad; });
                    if (acik) { liste.push(ad); }
                    yazBolumler(liste);
                });
            }

            /* ---------- Menüde arama ----------
               39 madde arasında yazarak süzer; eşleşen madde varsa
               bölümü geçici olarak açar. */
            var kutu = document.getElementById('y-menu-ara');
            var bosMesaj = document.getElementById('y-bos-arama');

            if (kutu && menu) {
                var bolumler = Array.prototype.slice.call(menu.querySelectorAll('.y-bolum'));

                kutu.addEventListener('input', function () {
                    var q = kutu.value.trim().toLocaleLowerCase('tr');
                    var bulunan = 0;

                    bolumler.forEach(function (bolum) {
                        var ogeler = Array.prototype.slice.call(bolum.querySelectorAll('.y-oge'));
                        var eslesen = 0;

                        ogeler.forEach(function (oge) {
                            var ad = (oge.querySelector('.y-oge-ad') || {}).textContent || '';
                            var uyar = q === '' || ad.toLocaleLowerCase('tr').indexOf(q) !== -1;
                            oge.style.display = uyar ? '' : 'none';
                            if (uyar) { eslesen++; }
                        });

                        bulunan += eslesen;
                        bolum.style.display = (q !== '' && eslesen === 0) ? 'none' : '';

                        if (q !== '') {
                            bolum.classList.add('acik');
                        } else {
                            /* Arama temizlenince eski duruma dön */
                            var ad = bolum.dataset.bolum;
                            var acikOlmali = bolum.dataset.varsayilan === '1'
                                || okunanBolumler().indexOf(ad) !== -1;
                            bolum.classList.toggle('acik', acikOlmali);
                        }
                    });

                    if (bosMesaj) {
                        bosMesaj.style.display = (q !== '' && bulunan === 0) ? 'block' : 'none';
                    }
                });

                /* Sayfa açılışındaki durumu işaretle ki arama temizlenince geri dönebilelim */
                bolumler.forEach(function (b) {
                    if (b.classList.contains('acik')) { b.dataset.varsayilan = '1'; }
                });

                /* Ctrl/Cmd + K ile menü aramasına odaklan */
                document.addEventListener('keydown', function (olay) {
                    if ((olay.ctrlKey || olay.metaKey) && olay.key.toLowerCase() === 'k') {
                        olay.preventDefault();
                        kutu.focus();
                        kutu.select();
                    }
                });
            }

            /* ---------- Küçük ekranda menü ---------- */
            var kenar = document.getElementById('y-kenar');
            var perde = document.getElementById('y-perde');
            var ac = document.getElementById('y-menu-ac');

            function menuKapat() {
                if (kenar) { kenar.classList.remove('acik'); }
                if (perde) { perde.classList.remove('acik'); }
            }

            if (ac) {
                ac.addEventListener('click', function () {
                    kenar.classList.toggle('acik');
                    if (perde) { perde.classList.toggle('acik'); }
                });
            }
            if (perde) { perde.addEventListener('click', menuKapat); }
            document.addEventListener('keydown', function (o) {
                if (o.key === 'Escape') { menuKapat(); }
            });

            /* ---------- Tema ---------- */
            var temaDugme = document.getElementById('y-tema');
            if (temaDugme) {
                temaDugme.addEventListener('click', function () {
                    var kok = document.documentElement;
                    var yeni = kok.getAttribute('data-tema') === 'koyu' ? 'acik' : 'koyu';
                    kok.setAttribute('data-tema', yeni);
                    document.cookie = 'vhm_panel_tema=' + yeni + ';path=/;max-age=31536000;samesite=Lax';
                    var ikon = temaDugme.querySelector('i');
                    if (ikon) {
                        ikon.className = 'fas ' + (yeni === 'koyu' ? 'fa-sun' : 'fa-moon');
                    }
                });
            }
        })();

        /* ---------- Pencere yardımcıları (sayfalar kullanıyor) ---------- */
        function openModal(id) {
            var m = document.getElementById(id);
            if (m) { m.classList.add('active'); }
        }

        function closeModal(id) {
            var m = document.getElementById(id);
            if (m) { m.classList.remove('active'); }
        }

        /* ---------- Durum değiştiren istekler ----------
           Eskiden adrese gidiliyordu; giriş yapmış bir yöneticiye
           gösterilen zararlı bir sayfa, onun adına kayıt sildirebiliyordu.
           Artık belirteçli POST gönderilir. */
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

        function confirmDelete(message, url) {
            if (confirm(message || 'Bu kaydı silmek istediğinizden emin misiniz?')) {
                gonderPost(url);
            }
        }
    </script>
</body>

</html>
