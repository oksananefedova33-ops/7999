(function(){
    'use strict';
    
    let remoteSites = [];
    
    // Добавляем кнопку "Мои сайты" в тулбар
    function addRemoteSitesButton() {
        const toolbar = document.querySelector('.topbar');
        if (!toolbar || document.getElementById('btnRemoteSites')) return;
        
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.id = 'btnRemoteSites';
        btn.className = 'btn';
        btn.textContent = '🌐 Мои сайты';
        btn.addEventListener('click', openRemoteSitesModal);
        
        // Вставляем после кнопки Экспорт
        const exportBtn = toolbar.querySelector('#btnExport');
        if (exportBtn) {
            exportBtn.parentNode.insertBefore(btn, exportBtn.nextSibling);
        }
    }
    
    // Создаем модальное окно
    function createModal() {
        if (document.getElementById('rsModalBackdrop')) return;
        
        const backdrop = document.createElement('div');
        backdrop.id = 'rsModalBackdrop';
        backdrop.className = 'rs-backdrop hidden';
        
        const modal = document.createElement('div');
        modal.className = 'rs-modal';
        
        modal.innerHTML = `
            <div class="rs-modal__header">
                <div class="rs-modal__title">🌐 Управление удаленными сайтами</div>
                <button type="button" class="rs-close">×</button>
            </div>
            <div class="rs-modal__body">
                <div class="rs-section">
                    <div class="rs-add-site">
                        <input type="text" class="rs-input" id="rsDomain" placeholder="https://example.com">
                        <input type="password" class="rs-input" id="rsToken" placeholder="Токен доступа">
                        <button class="rs-btn primary" id="rsAddSite">➕ Добавить сайт</button>
                    </div>
                </div>
                
                <div class="rs-section">
                    <div class="rs-label">Подключенные сайты:</div>
                    <div id="rsSitesList" class="rs-sites-list"></div>
                </div>
                
                <div id="rsSearchSection" class="rs-section hidden">
                    <div class="rs-label">Поиск на выбранном сайте:</div>
                    <div class="rs-search-controls">
                        <select id="rsSearchType" class="rs-select">
                            <option value="files">📁 Файлы</option>
                            <option value="links">🔗 Ссылки</option>
                        </select>
                        <input type="text" class="rs-input" id="rsSearchQuery" placeholder="Поисковый запрос">
                        <button class="rs-btn" id="rsSearch">🔍 Найти</button>
                    </div>
                    <div id="rsSearchResults" class="rs-results"></div>
                </div>
                
                <div id="rsReplaceSection" class="rs-section hidden">
                    <div class="rs-label">Массовая замена:</div>
                    <div class="rs-replace-controls">
                        <input type="text" class="rs-input" id="rsReplaceFrom" placeholder="Что заменить">
                        <input type="text" class="rs-input" id="rsReplaceTo" placeholder="На что заменить">
                        <button class="rs-btn danger" id="rsReplace">⚡ Заменить на всех сайтах</button>
                    </div>
                </div>
                
                <div id="rsStatus" class="rs-status"></div>
            </div>
        `;
        
        backdrop.appendChild(modal);
        document.body.appendChild(backdrop);
        
        // Обработчики
        modal.querySelector('.rs-close').addEventListener('click', closeModal);
        backdrop.addEventListener('click', (e) => {
            if (e.target === backdrop) closeModal();
        });
        
        document.getElementById('rsAddSite').addEventListener('click', addRemoteSite);
        document.getElementById('rsSearch').addEventListener('click', searchOnSite);
        document.getElementById('rsReplace').addEventListener('click', replaceOnAllSites);
    }
    
    function openRemoteSitesModal() {
        createModal();
        loadSites();
        document.getElementById('rsModalBackdrop').classList.remove('hidden');
    }
    
    function closeModal() {
        document.getElementById('rsModalBackdrop').classList.add('hidden');
    }
    
    async function addRemoteSite() {
        const domain = document.getElementById('rsDomain').value.trim();
        const token = document.getElementById('rsToken').value.trim();
        
        if (!domain || !token) {
            showStatus('Введите домен и токен', 'error');
            return;
        }
        
        showStatus('Проверка соединения...', 'info');
        
        try {
            // Проверяем соединение
            const response = await fetch('/editor/remote-sites-api.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `action=checkConnection&domain=${encodeURIComponent(domain)}&token=${encodeURIComponent(token)}`
            });
            
            const data = await response.json();
            
            if (data.ok) {
                // Сохраняем сайт
                const saveResponse = await fetch('/editor/remote-sites-api.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: `action=addSite&domain=${encodeURIComponent(domain)}&token=${encodeURIComponent(token)}`
                });
                
                const saveData = await saveResponse.json();
                if (saveData.ok) {
                    showStatus(`✅ Сайт ${domain} подключен`, 'success');
                    document.getElementById('rsDomain').value = '';
                    document.getElementById('rsToken').value = '';
                    loadSites();
                }
            } else {
                showStatus('❌ Не удалось подключиться: ' + (data.error || 'Проверьте домен и токен'), 'error');
            }
        } catch (error) {
            showStatus('❌ Ошибка: ' + error.message, 'error');
        }
    }
    
    async function loadSites() {
        try {
            const response = await fetch('/editor/remote-sites-api.php?action=getSites');
            const data = await response.json();
            
            if (data.ok && data.sites) {
                remoteSites = data.sites;
                renderSites();
            }
        } catch (error) {
            showStatus('Ошибка загрузки сайтов', 'error');
        }
    }
    
    function renderSites() {
        const list = document.getElementById('rsSitesList');
        if (!remoteSites.length) {
            list.innerHTML = '<div class="rs-empty">Нет подключенных сайтов</div>';
            document.getElementById('rsSearchSection').classList.add('hidden');
            document.getElementById('rsReplaceSection').classList.add('hidden');
            return;
        }
        
        list.innerHTML = remoteSites.map(site => `
            <div class="rs-site-item" data-id="${site.id}">
                <input type="radio" name="selectedSite" value="${site.id}" id="site_${site.id}">
                <label for="site_${site.id}">
                    <span class="rs-site-domain">${site.domain}</span>
                    <span class="rs-site-status ${site.status}">${site.status === 'online' ? '🟢' : '🔴'}</span>
                </label>
                <button class="rs-btn-small danger" onclick="removeSite(${site.id})">×</button>
            </div>
        `).join('');
        
        document.getElementById('rsSearchSection').classList.remove('hidden');
        if (remoteSites.length > 1) {
            document.getElementById('rsReplaceSection').classList.remove('hidden');
        }
    }
    
    async function searchOnSite() {
        const selectedSite = document.querySelector('input[name="selectedSite"]:checked');
        if (!selectedSite) {
            showStatus('Выберите сайт', 'error');
            return;
        }
        
        const siteId = selectedSite.value;
        const site = remoteSites.find(s => s.id == siteId);
        const searchType = document.getElementById('rsSearchType').value;
        const query = document.getElementById('rsSearchQuery').value;
        
        showStatus('Поиск...', 'info');
        
        try {
            const response = await fetch('/editor/remote-sites-api.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `action=search&domain=${encodeURIComponent(site.domain)}&token=${encodeURIComponent(site.token)}&type=${searchType}&query=${encodeURIComponent(query)}`
            });
            
            const data = await response.json();
            
            if (data.ok) {
                renderSearchResults(data.results, searchType);
                showStatus(`Найдено: ${data.results.length}`, 'success');
            } else {
                showStatus('Ошибка поиска', 'error');
            }
        } catch (error) {
            showStatus('Ошибка: ' + error.message, 'error');
        }
    }
    
    function renderSearchResults(results, type) {
    const container = document.getElementById('rsSearchResults');
    if (!results.length) {
        container.innerHTML = '<div class="rs-empty">Ничего не найдено</div>';
        return;
    }
    
    container.innerHTML = `
        <div class="rs-results-list">
            ${results.map(item => {
                const icon = type === 'files' ? '📁' : '🔗';
                const displayName = type === 'files' 
                    ? (item.name || item.url) 
                    : (item.text ? `"${item.text}" → ${item.url}` : item.url);
                
                return `
                    <div class="rs-result-item">
                        ${icon} ${displayName}
                        <div class="rs-result-pages">Страницы: ${item.pages.join(', ')}</div>
                    </div>
                `;
            }).join('')}
        </div>
    `;
}
    
    async function replaceOnAllSites() {
        const from = document.getElementById('rsReplaceFrom').value;
        const to = document.getElementById('rsReplaceTo').value;
        
        if (!from || !to) {
            showStatus('Заполните оба поля', 'error');
            return;
        }
        
        if (!confirm(`Заменить "${from}" на "${to}" на всех сайтах?`)) return;
        
        showStatus('Замена на всех сайтах...', 'info');
        
        let successCount = 0;
        let errorCount = 0;
        
        for (const site of remoteSites) {
            try {
                const response = await fetch('/editor/remote-sites-api.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: `action=replace&domain=${encodeURIComponent(site.domain)}&token=${encodeURIComponent(site.token)}&from=${encodeURIComponent(from)}&to=${encodeURIComponent(to)}`
                });
                
                const data = await response.json();
                if (data.ok) {
                    successCount++;
                } else {
                    errorCount++;
                }
            } catch (error) {
                errorCount++;
            }
        }
        
        showStatus(`✅ Успешно: ${successCount}, ❌ Ошибок: ${errorCount}`, successCount > 0 ? 'success' : 'error');
    }
    
    window.removeSite = async function(id) {
        if (!confirm('Удалить сайт из списка?')) return;
        
        try {
            const response = await fetch('/editor/remote-sites-api.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `action=removeSite&id=${id}`
            });
            
            const data = await response.json();
            if (data.ok) {
                loadSites();
            }
        } catch (error) {
            showStatus('Ошибка удаления', 'error');
        }
    };
    
    function showStatus(message, type = 'info') {
        const status = document.getElementById('rsStatus');
        if (!status) return;
        
        status.className = 'rs-status ' + type;
        status.textContent = message;
        
        if (type !== 'info') {
            setTimeout(() => {
                status.textContent = '';
                status.className = 'rs-status';
            }, 3000);
        }
    }
    
    // Инициализация
    document.addEventListener('DOMContentLoaded', function() {
        addRemoteSitesButton();
    });
})();