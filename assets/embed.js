(function() {
    const scriptSrc = document.currentScript ? document.currentScript.src : '';
    const baseUrl = scriptSrc ? scriptSrc.substring(0, scriptSrc.lastIndexOf('/')) : '';
    const formUrl = baseUrl + '/form';

    const EasyLeadCapture = {
        render: function(selector, options = {}) {
            const target = document.querySelector(selector);
            if (!target) return;

            const mode = options.mode || 'inline';
            const url = options.formUrl || formUrl;
            const iframeId = 'elc-iframe-' + Math.random().toString(36).substr(2, 9);

            if (mode === 'modal') {
                this.renderModal(url, iframeId);
            } else {
                this.renderInline(target, url, iframeId);
            }

            this.setupResizeListener(iframeId);
        },

        renderInline: function(target, url, iframeId) {
            const iframe = document.createElement('iframe');
            iframe.id = iframeId;
            iframe.src = url;
            iframe.style.width = '100%';
            iframe.style.border = 'none';
            iframe.style.overflow = 'hidden';
            iframe.scrolling = 'no';
            target.appendChild(iframe);
        },

        renderModal: function(url, iframeId) {
            const overlay = document.createElement('div');
            overlay.id = 'elc-modal-overlay';
            overlay.style.position = 'fixed';
            overlay.style.top = '0';
            overlay.style.left = '0';
            overlay.style.width = '100%';
            overlay.style.height = '100%';
            overlay.style.backgroundColor = 'rgba(0,0,0,0.5)';
            overlay.style.display = 'flex';
            overlay.style.alignItems = 'center';
            overlay.style.justifyContent = 'center';
            overlay.style.zIndex = '9999';
            overlay.style.padding = '20px';

            const container = document.createElement('div');
            container.style.width = '100%';
            container.style.maxWidth = '500px';
            container.style.position = 'relative';
            container.style.backgroundColor = 'transparent';

            const closeBtn = document.createElement('button');
            closeBtn.innerHTML = '&times;';
            closeBtn.style.position = 'absolute';
            closeBtn.style.top = '-40px';
            closeBtn.style.right = '0';
            closeBtn.style.backgroundColor = 'transparent';
            closeBtn.style.border = 'none';
            closeBtn.style.color = '#fff';
            closeBtn.style.fontSize = '30px';
            closeBtn.style.cursor = 'pointer';
            closeBtn.onclick = () => document.body.removeChild(overlay);

            const iframe = document.createElement('iframe');
            iframe.id = iframeId;
            iframe.src = url;
            iframe.style.width = '100%';
            iframe.style.border = 'none';
            iframe.style.borderRadius = '12px';
            iframe.style.overflow = 'hidden';
            iframe.scrolling = 'no';

            container.appendChild(closeBtn);
            container.appendChild(iframe);
            overlay.appendChild(container);
            document.body.appendChild(overlay);

            overlay.onclick = (e) => {
                if (e.target === overlay) document.body.removeChild(overlay);
            };

            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && document.getElementById('elc-modal-overlay')) {
                    document.body.removeChild(overlay);
                }
            }, { once: true });
        },

        setupResizeListener: function(iframeId) {
            window.addEventListener('message', function(e) {
                if (e.data && e.data.type === 'elc-resize' && e.data.height) {
                    const iframe = document.getElementById(iframeId);
                    if (iframe) {
                        iframe.style.height = e.data.height + 'px';
                    }
                }
            });
        }
    };

    window.EasyLeadCapture = EasyLeadCapture;
})();
