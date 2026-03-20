

const CW = 770, CH = 550;
const canvas = document.getElementById('c');
const ctx    = canvas.getContext('2d');
canvas.width  = CW;
canvas.height = CH;


const mapData = window.MAP_DATA;   // injecté par index.php
let nodeMap   = {};
let trains    = [], pops = [];
let score     = 0, spawnIndex = 0, spawnTimer = 0, lastTime = 0;
let gameState = 'idle';            // idle | running | paused | over
let gameOverPending = false;


function initMap() {
    nodeMap = {};
    mapData.nodes.forEach(n => nodeMap[n.id] = n);
    score = 0; spawnIndex = 0; spawnTimer = 0;
    trains = []; pops = []; gameOverPending = false;
    document.getElementById('h-rem').textContent   = mapData.queue.length;
    document.getElementById('h-on').textContent    = 0;
    document.getElementById('h-score').textContent = 0;
}


function trySpawn() {
    if (trains.some(t => t.alive)) return false;    // un train déjà en route
    if (spawnIndex >= mapData.queue.length) return false;

    const spec = mapData.queue[spawnIndex++];
    const sn   = nodeMap[mapData.tunnelNodeId];

    trains.push({
        color:      spec.color,
        curNode:    sn,
        prevNodeId: -1,
        nextNode:   null,
        x: sn.x, y: sn.y,
        progress: 1,
        speed:    mapData.cfg.trainSpeed,
        alive:    true,
        size:     14,
    });

    updateHUD();
    return true;
}


/**
 * Calcule le prochain nœud depuis `cur`, en excluant `prevId`
 * (interdit le demi-tour).
 * Respecte l'état de la jonction si cur.isJunction.
 */
function nextNodeFor(cur, prevId) {
    const outs = cur.connections.filter(id => id !== prevId);
    if (!outs.length) return null;                              // cul-de-sac
    if (!cur.isJunction || outs.length === 1) return nodeMap[outs[0]];
    return nodeMap[outs[cur.junctionState % outs.length]];
}

function advanceTrain(t) {
    const next = nextNodeFor(t.curNode, t.prevNodeId);
    if (!next) { t.alive = false; triggerGameOver(t, 'lost'); return; }
    t.nextNode = next;
    t.progress = 0;
}

function updateTrain(t, dt) {
    if (!t.alive) return;
    if (!t.nextNode) { advanceTrain(t); return; }

    const dx   = t.nextNode.x - t.curNode.x;
    const dy   = t.nextNode.y - t.curNode.y;
    const dist = Math.hypot(dx, dy) || 1;

    t.progress += (t.speed * dt * 60) / dist;
    t.x = t.curNode.x + dx * t.progress;
    t.y = t.curNode.y + dy * t.progress;

    if (t.progress >= 1) {
        t.prevNodeId = t.curNode.id;
        t.curNode    = t.nextNode;
        t.nextNode   = null;

        // Arrivée sur un terminus ?
        if (t.curNode.isTerminus) {
            t.alive = false;
            const st = mapData.stations.find(s => s.terminusNodeId === t.curNode.id);

            if (st && st.color.id === t.color.id) {
                // ✅ Bonne gare → +1 point
                score++;
                pops.push({ x: t.x, y: t.y - 20, txt: '+1 ✅', color: st.color.hex, life: 1.2 });
                document.getElementById('h-score').textContent = score;
            } else {
                // ❌ Mauvaise gare → 0 point, pas de game over
                pops.push({ x: t.x, y: t.y - 20, txt: '0 ❌', color: '#ef5350', life: 1.5 });
            }
        } else if (t.alive) {
            advanceTrain(t);
        }

        updateHUD();
    }
}


function updateHUD() {
    document.getElementById('h-rem').textContent = Math.max(0, mapData.queue.length - spawnIndex);
    document.getElementById('h-on').textContent  = trains.filter(t => t.alive).length;
}


function triggerGameOver(t, reason) {
    if (gameOverPending || gameState !== 'running') return;
    gameOverPending = true;

    setTimeout(() => {
        gameState = 'over';
        const msg = reason === 'wrong' ? 'a raté sa gare !' : 'est sorti du réseau !';
        document.getElementById('ov-title').textContent = '💥 GAME OVER !';
        document.getElementById('ov-sub').innerHTML =
            `Le train <span style="color:${t.color.hex};font-weight:800">${t.color.label}</span> ${msg}<br>` +
            `Score : <strong style="color:#ffee58">${score} / ${mapData.queue.length}</strong>`;
        document.getElementById('ov-score').style.display = 'block';
        document.getElementById('ov-score').textContent   = score + ' pts';
        document.getElementById('btn-ov-start').textContent = '↺ Rejouer';
        document.getElementById('overlay').classList.remove('hidden');
        gameOverPending = false;
    }, 500);
}

function endGame() {
    gameState = 'over';
    const tot = mapData.queue.length;
    const pct = Math.round(score / tot * 100);
    const title = pct >= 100 ? '🏆 PARFAIT !'
                : pct >= 80  ? '🏆 Excellent !'
                : pct >= 50  ? '👍 Bien joué !'
                :              '😅 À retenter !';
    document.getElementById('ov-title').textContent       = title;
    document.getElementById('ov-sub').textContent         = `Score : ${score} / ${tot} (${pct}%)`;
    document.getElementById('ov-score').style.display     = 'block';
    document.getElementById('ov-score').textContent       = score + ' pts';
    document.getElementById('btn-ov-start').textContent   = '↺ Rejouer';
    document.getElementById('overlay').classList.remove('hidden');
}


function rRect(x, y, w, h, r, fill, stroke, lw) {
    ctx.beginPath();
    ctx.roundRect(x, y, w, h, r);
    if (fill && fill !== 'none')   { ctx.fillStyle = fill;     ctx.fill();   }
    if (stroke && stroke !== 'none' && lw) { ctx.strokeStyle = stroke; ctx.lineWidth = lw; ctx.stroke(); }
}


function drawBG() {
    ctx.fillStyle = '#3b7a3b';
    ctx.fillRect(0, 0, CW, CH);
    ctx.strokeStyle = 'rgba(0,0,0,.06)';
    ctx.lineWidth = 1;
    for (let x = 0; x < CW; x += 22) { ctx.beginPath(); ctx.moveTo(x, 0);  ctx.lineTo(x, CH); ctx.stroke(); }
    for (let y = 0; y < CH; y += 22) { ctx.beginPath(); ctx.moveTo(0, y);  ctx.lineTo(CW, y); ctx.stroke(); }
    if (gameOverPending) { ctx.fillStyle = 'rgba(200,0,0,.14)'; ctx.fillRect(0, 0, CW, CH); }
}


function drawRails() {
    const done = new Set();
    mapData.nodes.forEach(n => {
        n.connections.forEach(nid => {
            const key = [Math.min(n.id, nid), Math.max(n.id, nid)].join('-');
            if (done.has(key)) return;
            done.add(key);
            const b = nodeMap[nid];
            if (b) drawRailSeg(n.x, n.y, b.x, b.y);
        });
    });
}

function drawRailSeg(x1, y1, x2, y2) {
    ctx.lineCap = 'round';
    // Fond (ballast)
    ctx.strokeStyle = '#1b3a1b'; ctx.lineWidth = 18;
    ctx.beginPath(); ctx.moveTo(x1, y1); ctx.lineTo(x2, y2); ctx.stroke();
    // Couche verte
    ctx.strokeStyle = '#2d5e1a'; ctx.lineWidth = 13;
    ctx.beginPath(); ctx.moveTo(x1, y1); ctx.lineTo(x2, y2); ctx.stroke();

    // Rails argentés
    const dx = x2 - x1, dy = y2 - y1, len = Math.hypot(dx, dy) || 1;
    const px = -dy / len * 5, py = dx / len * 5;
    [1, -1].forEach(s => {
        ctx.strokeStyle = '#cfd8dc'; ctx.lineWidth = 3;
        ctx.beginPath(); ctx.moveTo(x1 + px * s, y1 + py * s); ctx.lineTo(x2 + px * s, y2 + py * s); ctx.stroke();
    });

    // Traverses
    const steps = Math.max(2, Math.floor(len / 26));
    ctx.strokeStyle = '#4a7a2a'; ctx.lineWidth = 3.5; ctx.lineCap = 'square';
    for (let i = 1; i < steps; i++) {
        const t = i / steps;
        const mx = x1 + dx * t, my = y1 + dy * t;
        ctx.beginPath(); ctx.moveTo(mx + px * 1.4, my + py * 1.4); ctx.lineTo(mx - px * 1.4, my - py * 1.4); ctx.stroke();
    }
    ctx.lineCap = 'round';
}


function drawJunctions() {
    mapData.nodes.forEach(n => {
        if (!n.isJunction) return;

        if (n.isStationEntry) {
            // Jonction colorée devant une gare
            const st = mapData.stations.find(s => s.entryNodeId === n.id);
            const c  = st ? st.color.hex : '#fff';
            ctx.beginPath(); ctx.arc(n.x, n.y, 27, 0, Math.PI * 2); ctx.fillStyle = c + '55'; ctx.fill();
            ctx.beginPath(); ctx.arc(n.x, n.y, 19, 0, Math.PI * 2);
            ctx.fillStyle = c; ctx.strokeStyle = '#fff'; ctx.lineWidth = 3; ctx.fill(); ctx.stroke();
        } else {
            // Jonction normale (verte / jaune selon état)
            const on = n.junctionState === 0;
            ctx.beginPath(); ctx.arc(n.x, n.y, 28, 0, Math.PI * 2);
            ctx.fillStyle = on ? 'rgba(76,175,80,.25)' : 'rgba(255,238,88,.25)'; ctx.fill();
            ctx.beginPath(); ctx.arc(n.x, n.y, 20, 0, Math.PI * 2);
            ctx.fillStyle = on ? '#4caf50' : '#ffee58';
            ctx.strokeStyle = '#1b5e20'; ctx.lineWidth = 3.5; ctx.fill(); ctx.stroke();
        }

        // Flèche directionnelle
        const outs = n.connections;
        if (outs.length) {
            const target = nodeMap[outs[n.junctionState % outs.length]];
            if (target) drawArrow(n, target, '#1b5e20');
        }
    });
}

function drawArrow(from, to, color) {
    const dx = to.x - from.x, dy = to.y - from.y, len = Math.hypot(dx, dy) || 1;
    const ex = from.x + dx / len * 12, ey = from.y + dy / len * 12;
    ctx.strokeStyle = color; ctx.lineWidth = 2.5;
    ctx.beginPath(); ctx.moveTo(from.x - dx / len * 5, from.y - dy / len * 5); ctx.lineTo(ex, ey); ctx.stroke();
    const a = Math.atan2(dy, dx);
    ctx.beginPath(); ctx.moveTo(ex, ey);
    ctx.lineTo(ex - Math.cos(a - .38) * 9, ey - Math.sin(a - .38) * 9);
    ctx.lineTo(ex - Math.cos(a + .38) * 9, ey - Math.sin(a + .38) * 9);
    ctx.closePath(); ctx.fillStyle = color; ctx.fill();
}


function drawStations() {
    mapData.stations.forEach(st => {
        const n = nodeMap[st.terminusNodeId];
        if (!n) return;
        const side = n.y < CH / 2 ? 'top' : 'bottom';
        ctx.save(); ctx.translate(n.x, n.y);
        if (side === 'bottom') ctx.rotate(Math.PI);

        const s = 30, c = st.color;
        rRect(-s * .65, -s * .5, s * 1.3, s * 1.0, 6, c.hex, '#fff', 3);
        // Toit triangulaire
        ctx.beginPath(); ctx.moveTo(-s * .8, -s * .47); ctx.lineTo(0, -s * 1.08); ctx.lineTo(s * .8, -s * .47);
        ctx.closePath(); ctx.fillStyle = c.hex; ctx.strokeStyle = '#fff'; ctx.lineWidth = 3; ctx.fill(); ctx.stroke();
        // Cheminée
        ctx.fillStyle = '#fff'; ctx.fillRect(-4, -s * 1.22, 8, 18);
        ctx.beginPath(); ctx.moveTo(4, -s * 1.25); ctx.lineTo(15, -s * 1.14); ctx.lineTo(4, -s * 1.03);
        ctx.closePath(); ctx.fillStyle = c.hex; ctx.fill();
        // Porte
        ctx.fillStyle = c.dark; ctx.fillRect(-7, s * .1, 14, s * .48);
        ctx.beginPath(); ctx.arc(0, s * .1, 7, Math.PI, 0); ctx.fillStyle = c.dark; ctx.fill();
        // Fenêtres
        ctx.fillStyle = 'rgba(255,255,255,.8)';
        [[-s * .34, -s * .1], [s * .34, -s * .1]].forEach(([fx, fy]) => ctx.fillRect(fx - 6, fy - 6, 12, 12));
        ctx.restore();
    });
}


function drawTunnel() {
    const n = nodeMap[mapData.tunnelNodeId];
    if (!n) return;
    ctx.save(); ctx.translate(n.x, n.y);
    const c = '#6a1b9a';
    rRect(-18, -34, 36, 34, 7, c, '#fff', 2.5);
    ctx.beginPath(); ctx.arc(0, -40, 13, 0, Math.PI * 2);
    ctx.fillStyle = '#ce93d8'; ctx.strokeStyle = '#fff'; ctx.lineWidth = 2.5; ctx.fill(); ctx.stroke();
    ctx.fillStyle = '#fff';
    ctx.beginPath(); ctx.moveTo(-5, -52); ctx.lineTo(0, -60); ctx.lineTo(5, -52); ctx.fill();
    [-9, 9].forEach(dx => rRect(dx - 4, 0, 8, 14, 2, c, '#fff', 1.5));
    ctx.fillStyle = 'rgba(255,255,255,.55)';
    [[-8, -24], [8, -24]].forEach(([fx, fy]) => ctx.fillRect(fx - 4, fy - 4, 8, 8));
    ctx.font = 'bold 13px Arial'; ctx.fillStyle = '#fff';
    ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
    ctx.fillText('🚂', 0, -40);
    ctx.restore();
}


function drawTrains() {
    trains.forEach(t => { if (t.alive) drawTrain(t); });
}

function drawTrain(t) {
    const angle = t.nextNode
        ? Math.atan2(t.nextNode.y - t.curNode.y, t.nextNode.x - t.curNode.x)
        : 0;
    ctx.save(); ctx.translate(t.x, t.y); ctx.rotate(angle);
    const w = t.size * 2.7, h = t.size * 1.75;

    ctx.fillStyle = 'rgba(0,0,0,.3)'; ctx.fillRect(-w / 2 + 3, -h / 2 + 4, w, h);
    rRect(-w / 2, -h / 2, w, h, 6, t.color.hex, '#fff', 2.5);

    // Cabine
    ctx.fillStyle = t.color.dark || '#333'; ctx.strokeStyle = '#fff'; ctx.lineWidth = 1.5;
    ctx.beginPath(); ctx.roundRect(w * .1, -h / 2, w * .34, h * .7, [3, 3, 0, 0]); ctx.fill(); ctx.stroke();

    // Fenêtre
    ctx.fillStyle = 'rgba(255,255,255,.85)'; ctx.fillRect(w * .13, -h / 2 + 3, w * .28, h * .4);

    // Roues
    [-w * .3, w * .15].forEach(rx => {
        ctx.beginPath(); ctx.arc(rx, h / 2 - 1, 4.5, 0, Math.PI * 2);
        ctx.fillStyle = '#37474f'; ctx.fill();
        ctx.strokeStyle = '#90a4ae'; ctx.lineWidth = 1.2; ctx.stroke();
    });

    // Phare
    ctx.beginPath(); ctx.arc(w / 2 - 2, 0, 4, 0, Math.PI * 2);
    ctx.fillStyle = '#fffde7'; ctx.fill();
    ctx.restore();
}


function drawPops(dt) {
    for (let i = pops.length - 1; i >= 0; i--) {
        const p = pops[i];
        p.life -= dt * 1.3;
        p.y    -= dt * 30;
        if (p.life <= 0) { pops.splice(i, 1); continue; }
        ctx.globalAlpha = Math.min(1, p.life);
        ctx.font        = `bold ${16 + (1 - p.life) * 10}px 'Fredoka One', cursive`;
        ctx.fillStyle   = p.color;
        ctx.textAlign   = 'center';
        ctx.fillText(p.txt, p.x, p.y);
        ctx.globalAlpha = 1;
    }
}


function loop(ts) {
    const dt = Math.min((ts - lastTime) / 1000, .05);
    lastTime = ts;

    if (gameState === 'running') {
        spawnTimer += dt * 1000;
        if (spawnTimer >= mapData.cfg.spawnInterval) {
            if (trySpawn()) spawnTimer = 0;
        }
        trains.forEach(t => updateTrain(t, dt));
        trains = trains.filter(t => t.alive);
        if (spawnIndex >= mapData.queue.length && trains.length === 0 && !gameOverPending) {
            endGame();
        }
    }

    drawBG(); drawRails(); drawJunctions(); drawStations(); drawTunnel(); drawTrains(); drawPops(dt);
    requestAnimationFrame(loop);
}


canvas.addEventListener('click', e => {
    if (gameState !== 'running') return;
    const r  = canvas.getBoundingClientRect();
    const mx = (e.clientX - r.left) * (CW / r.width);
    const my = (e.clientY - r.top)  * (CH / r.height);

    mapData.nodes.forEach(n => {
        if (!n.isJunction) return;
        if (Math.hypot(mx - n.x, my - n.y) < 28) {
            n.junctionState = (n.junctionState + 1) % Math.max(2, n.connections.length);
            pops.push({ x: n.x, y: n.y - 26, txt: '↔', color: n.isStationEntry ? '#fff' : '#ffee58', life: 0.65 });
        }
    });
});

document.getElementById('btn-start').onclick = startGame;
document.getElementById('btn-ov-start').onclick = () => window.location.reload();
document.getElementById('btn-pause').onclick = () => {
    if (gameState === 'running') {
        gameState = 'paused';
        document.getElementById('btn-pause').textContent = '▶ Reprendre';
    } else if (gameState === 'paused') {
        gameState = 'running';
        document.getElementById('btn-pause').textContent = '⏸ Pause';
    }
};

function startGame() {
    initMap();
    gameState  = 'running';
    spawnTimer = mapData.cfg.spawnInterval; // spawn immédiat au démarrage
    document.getElementById('overlay').classList.add('hidden');
    document.getElementById('btn-pause').textContent = '⏸ Pause';
}


initMap();
requestAnimationFrame(ts => { lastTime = ts; requestAnimationFrame(loop); });
