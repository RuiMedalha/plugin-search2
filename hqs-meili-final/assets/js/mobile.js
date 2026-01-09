/* ============================================================
 * HQS MOBILE JS (V2 - Header & Close Button)
 * ============================================================ */
(function() {
    const isMobile = window.innerWidth <= 768;
    if (!isMobile) return; 
 
    function toggleBodyScroll(lock) {
        document.body.style.overflow = lock ? 'hidden' : '';
    }
 
    // Função para injetar o Header Mobile (Opção A)
    function injectMobileHeader(dropdown) {
        const container = dropdown.querySelector('.hqs-container');
        if (!container || container.querySelector('.hqs-mobile-header')) return;
 
        const header = document.createElement('div');
        header.className = 'hqs-mobile-header';
        header.innerHTML = `
            <span class="hqs-mob-title">Resultados</span>
            <button type="button" class="hqs-mob-close" aria-label="Fechar">&times;</button>
        `;
 
        // Insere no início do container
        container.insertBefore(header, container.firstChild);
 
        // Evento de fechar
        const closeBtn = header.querySelector('.hqs-mob-close');
        closeBtn.addEventListener('click', function(e) {
            e.preventDefault();
            dropdown.style.display = 'none';
            dropdown.classList.remove('hqs-open');
            toggleBodyScroll(false);
            
            // Opcional: Limpar o input se quiseres resetar a pesquisa
            // const input = document.querySelector('input[data-hqs="1"]');
            // if(input) input.value = '';
        });
    }
 
    const observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            if (mutation.target.id === 'hqs-dropdown') {
                const dropdown = mutation.target;
                
                // 1. Injetar o header se ainda não existir
                if (dropdown.querySelector('.hqs-container')) {
                    injectMobileHeader(dropdown);
                }
 
                // 2. Gerir Scroll
                if (dropdown.style.display === 'block' && dropdown.style.display !== 'none') {
                    toggleBodyScroll(true);
                    // Forçar input blur para fechar teclado mobile e vermos os resultados
                    const input = document.querySelector('input[data-hqs="1"]');
                    if (input && document.activeElement === input) {
                        input.blur();
                    }
                } else {
                    toggleBodyScroll(false);
                }
            }
        });
    });
 
    const checkExist = setInterval(function() {
        const dropdown = document.getElementById('hqs-dropdown');
        if (dropdown) {
            observer.observe(dropdown, { attributes: true, attributeFilter: ['style', 'class'] });
            // Tenta injetar logo na primeira deteção caso já esteja renderizado
            injectMobileHeader(dropdown);
            clearInterval(checkExist);
        }
    }, 200);
 
})();