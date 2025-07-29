/* global aicaAudit */
document.addEventListener('DOMContentLoaded', () => {

    const input    = document.querySelector('.audit-url');
    const runBtn   = document.querySelector('.run-audit');
    const outWrap  = document.querySelector('.results-container');
    const outPre   = document.querySelector('.audit-output');
    const kwBtn    = document.querySelector('.apply-keywords');

    if (!input || !runBtn || !outWrap || !outPre || !kwBtn) {
        return; // Elements not found, probably not on audit tab
    }

    async function runAudit() {
        if (!input.value) return;
        runBtn.disabled = true;
        runBtn.textContent = 'Analyzing...';

        try {
            const res = await fetch(`${aicaAudit.apiUrl}?url=${encodeURIComponent(input.value)}`, {
                headers: { 'X-WP-Nonce': aicaAudit.nonce }
            });

            if (res.status === 429) {
                outPre.textContent = 'Rate limited. Please wait 2 seconds and try again.';
                outWrap.style.display = 'block';
                setTimeout(() => {
                    runBtn.disabled = false;
                    runBtn.textContent = 'Analyze';
                }, 2000);
                return;
            }

            const data = await res.json();
            outPre.textContent = JSON.stringify(data, null, 2);
            outWrap.style.display = 'block';
        } catch (error) {
            outPre.textContent = 'Error: ' + error.message;
            outWrap.style.display = 'block';
        }

        runBtn.disabled = false;
        runBtn.textContent = 'Analyze';
    }

    runBtn.addEventListener('click', runAudit);

    kwBtn.addEventListener('click', async () => {
        const kw = prompt('Enter comma-separated keywords to apply:');
        if (!kw) return;
        
        try {
            await fetch('/wp-json/aica/v1/strategy/keywords', {
                method: 'POST',
                headers: {
                    'X-WP-Nonce': aicaAudit.nonce,
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ keywords: kw })
            });
            alert('Keywords saved to Strategy');
        } catch (error) {
            alert('Error saving keywords: ' + error.message);
        }
    });
});
