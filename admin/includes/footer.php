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
        
        // Silme onayı
        function confirmDelete(message, url) {
            if (confirm(message || 'Bu kaydı silmek istediğinizden emin misiniz?')) {
                window.location.href = url;
            }
        }
    </script>
</body>
</html>

