const canvas = document.getElementById('matrix');
const ctx = canvas.getContext('2d');

canvas.width = window.innerWidth;
canvas.height = window.innerHeight;

const chars = "-NULL-ASTER-NULL-ASTER-NULL-ASTER-NULL-ASTER-NULL-ASTER-NULL-ASTER";
const charArray = chars.split("");
const fontSize = 14;
const columns = canvas.width / fontSize;
const drops = [];

for(let i = 0; i < columns; i++) {
    drops[i] = Math.floor(Math.random() * canvas.height / fontSize);
}

function drawMatrix() {
    ctx.fillStyle = 'rgba(0, 0, 0, 0.04)';
    ctx.fillRect(0, 0, canvas.width, canvas.height);
    
    ctx.fillStyle = '#00FF00';
    ctx.font = fontSize + 'px monospace';
    
    for(let i = 0; i < drops.length; i++) {
        const text = charArray[Math.floor(Math.random() * charArray.length)];
        ctx.fillText(text, i * fontSize, drops[i] * fontSize);
        
        if(drops[i] * fontSize > canvas.height && Math.random() > 0.975) {
            drops[i] = 0;
        }
        
        drops[i]++;
    }
}

setInterval(drawMatrix, 35);

window.addEventListener('resize', function() {
    canvas.width = window.innerWidth;
    canvas.height = window.innerHeight;
    
    const newColumns = canvas.width / fontSize;
    drops.length = 0;
    
    for(let i = 0; i < newColumns; i++) {
        drops[i] = Math.floor(Math.random() * canvas.height / fontSize);
    }
});
