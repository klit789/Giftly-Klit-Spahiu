document.addEventListener('DOMContentLoaded', () => {
  const root = document.documentElement;
  document.querySelectorAll('[data-theme-toggle]').forEach(button => button.addEventListener('click', () => {
    const next = root.dataset.theme === 'dark' ? 'light' : 'dark'; root.dataset.theme = next; localStorage.setItem('giftly-theme', next);
  }));
  document.querySelectorAll('[data-count]').forEach(node => { const end=Number(node.dataset.count||0); if(!Number.isFinite(end))return; const start=performance.now(),duration=Math.min(1400,350+end*15); const tick=now=>{const p=Math.min(1,(now-start)/duration);node.textContent=Math.round(end*(1-Math.pow(1-p,3))).toLocaleString();if(p<1)requestAnimationFrame(tick)};requestAnimationFrame(tick); });
  if(document.body.dataset.confetti==='true'&&!window.matchMedia('(prefers-reduced-motion: reduce)').matches){const box=document.createElement('div');box.className='toast-confetti';for(let i=0;i<36;i++){const piece=document.createElement('i');piece.className='confetti';piece.style.left=`${Math.random()*100}%`;piece.style.setProperty('--drift',`${-160+Math.random()*320}px`);piece.style.background=['#F04427','#FFD928','#20201E'][i%3];piece.style.animationDelay=`${Math.random()*.45}s`;box.append(piece)}document.body.append(box);setTimeout(()=>box.remove(),2400)}
});
