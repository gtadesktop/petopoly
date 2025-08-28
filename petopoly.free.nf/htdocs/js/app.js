// js/app.js

// js/app.js

// 1) Richte den API‐Pfad einmal global ein:
const API_BASE = '/php/';


// 1) Helfer: JSON-API-Request
const API = (file, action, body) =>
  fetch(`php/${file}.php?action=${action}`, {
    headers: { 'Content-Type': 'application/json' },
    method: body ? 'POST' : 'GET',
    body: body ? JSON.stringify(body) : null
  }).then(r => {
    if (r.status === 204) return null;
    return r.json();
  });

let currentUser = null;
let isGuest = false;

// 2) Direkt beim Laden Auth-Bereich initialisieren
window.onload = () => bindAuth();

//
// AUTHENTIFIKATION & GASTZUGANG
//
// … ganz oben in app.js bleibt API() unverändert …

function bindAuth() {
  // Login
  document.getElementById('login-form').onsubmit = async e => {
    e.preventDefault();
    try {
      const f   = new FormData(e.target);
      const res = await API('auth','login',{
        username: f.get('username'),
        password: f.get('password')
      });
      handleAuth(res);
    } catch(err) {
      document.getElementById('auth-msg').innerText = 'Serverfehler beim Login';
    }
  };

  // Registrierung
  document.getElementById('register-form').onsubmit = async e => {
    e.preventDefault();
    try {
      const f   = new FormData(e.target);
      const res = await API('auth','register',{
        username: f.get('username'),
        password: f.get('password')
      });
      handleAuth(res);
    } catch(err) {
      document.getElementById('auth-msg').innerText = 'Serverfehler bei der Registrierung';
    }
  };

  // Gastzugang bleibt gleich …



  // Gastzugang
  document.getElementById('guest-btn').onclick = () => {
    isGuest = true;
    enterGame({ userId: 0 });
  };
}

function handleAuth(res) {
  if (res && res.status === 'ok') {
    enterGame(res);
  } else {
    document.getElementById('auth-msg').innerText =
      res.msg || 'Login/Registrierung fehlgeschlagen';
  }
}

//
// SPIELSTART NACH ERFOLG
//
function enterGame(user) {
  currentUser = user;
  document.getElementById('auth-container').style.display = 'none';
  document.getElementById('main-container').style.display = 'block';
  bindNav();
  loadView('shop');
}

//
// NAVIGATION & VIEWS
//
function bindNav() {
  document.querySelectorAll('nav button').forEach(btn => {
    btn.onclick = () => loadView(btn.dataset.view);
  });
}

async function loadView(view) {
  const c = document.getElementById('view-container');
  c.innerHTML = `<p>Lade ${view}…</p>`;

  switch (view) {
    case 'shop':
      renderShop(await API('pets', 'list'));
      break;
    case 'inventory':
      renderInventory(await API('pets', 'inventory'));
      break;
    case 'bank':
      renderBank(await API('bank', 'status'));
      break;
    case 'quests':
      renderQuests(await API('daily_quests', 'list'));
      break;
    case 'friends':
      renderFriends(await API('friend', 'list'));
      break;
    case 'news':
      renderNews(await API('news', 'get'));
      break;
    case 'arena':
      renderArena();
      break;
    case 'chat':
      renderChatList();
      break;
  }
}

//
// SHOP
//
function renderShop(pets) {
  const c = document.getElementById('view-container');
  c.innerHTML = '<h2>Shop</h2>';
  pets.forEach(p => {
    const d = document.createElement('div');
    d.className = 'card';
    d.innerHTML = `
      <h3>${p.name}</h3>
      <img src="img/${p.img}" width="80">
      <p>Preis: ${p.price} €</p>
      <button onclick="buyPet(${p.id})">Kaufen</button>`;
    c.append(d);
  });
}
async function buyPet(id) {
  const res = await API('pets', 'buy', { petId: id });
  alert(res.status === 'ok' ? 'Gekauft!' : 'Fehler: ' + res.msg);
  loadView('shop');
}

//
// INVENTAR
//
function renderInventory(inv) {
  const c = document.getElementById('view-container');
  c.innerHTML = '<h2>Meine Pets</h2>';
  if (!inv.length) return c.innerHTML += '<p>Keine Pets.</p>';
  inv.forEach(p => {
    const d = document.createElement('div');
    d.className = 'card';
    d.innerHTML = `
      <h3>${p.name}</h3>
      <img src="img/${p.img}" width="80">
      <p>Level: ${p.level}</p>
      <p>Siege: ${p.wins} | Niederlagen: ${p.losses}</p>`;
    c.append(d);
  });
}

//
// BANK
//
function renderBank(st) {
  const c = document.getElementById('view-container');
  c.innerHTML = `
    <h2>Bank</h2>
    <p>Balance: ${st.balance.toFixed(2)} €</p>
    <p>Sicherheit: ${st.collateral.toFixed(2)} €</p>
    <input id="loan-amt" type="number" placeholder="Kreditbetrag">
    <button onclick="applyLoan()">Beantragen</button>`;
}
async function applyLoan() {
  const amt = parseFloat(document.getElementById('loan-amt').value);
  const res = await API('bank', 'apply', { amount: amt });
  alert(res.status === 'approved' ? 'Bewilligt' : 'Abgelehnt');
  loadView('bank');
}

//
// DAILY QUESTS
//
function renderQuests(qs) {
  const c = document.getElementById('view-container');
  c.innerHTML = '<h2>Daily Quests</h2>';
  qs.forEach(q => {
    const d = document.createElement('div');
    d.className = 'card';
    d.innerHTML = `
      <h3>${q.name}</h3>
      <p>${q.description}</p>
      <p>Reward: ${q.reward} €</p>
      ${
        !q.completed
          ? `<button onclick="completeQuest(${q.id})">Erledigen</button>`
          : !q.claimed
          ? `<button onclick="claimQuest(${q.id})">Abholen</button>`
          : `<p>Abgeholt</p>`
      }`;
    c.append(d);
  });
}
async function completeQuest(id) {
  await API('daily_quests', 'complete', { quest_id: id });
  loadView('quests');
}
async function claimQuest(id) {
  const res = await API('daily_quests', 'claim', { quest_id: id });
  alert(res.status === 'claimed' ? 'Erhalten!' : 'Fehler');
  loadView('quests');
}

//
// FREUNDE
//
function renderFriends(list) {
  const c = document.getElementById('view-container');
  c.innerHTML = `
    <h2>Freunde</h2>
    <input id="fr-search" placeholder="Suche">
    <button onclick="searchFriends()">Suchen</button>
    <div id="fr-results"></div>
    <h3>Meine Freunde</h3>
    <ul>
      ${list
        .map(
          f => `
        <li>${f.friend_id}
          ${
            f.blocked
              ? `<button onclick="toggleBlock(${f.friend_id},false)">Entblocken</button>`
              : `<button onclick="toggleBlock(${f.friend_id},true)">Blockieren</button>`
          }
          <button onclick="removeFriend(${f.friend_id})">Entfernen</button>
        </li>`
        )
        .join('')}
    </ul>`;
}
async function searchFriends() {
  const q = document.getElementById('fr-search').value;
  const res = await API('friend', 'search', { username: q });
  const r = document.getElementById('fr-results');
  r.innerHTML = res
    .map(
      u => `<div>${u.username} <button onclick="sendFriend(${u.id})">Adden</button></div>`
    )
    .join('');
}
async function sendFriend(to) {
  await API('friend', 'send', { to_user: to });
  alert('Anfrage gesendet');
}
async function removeFriend(fid) {
  await API('friend', 'remove', { friend_id: fid });
  loadView('friends');
}
async function toggleBlock(fid, block) {
  await API('friend', block ? 'block' : 'unblock', { friend_id: fid });
  loadView('friends');
}

//
// NEWS
//
function renderNews(nw) {
  const c = document.getElementById('view-container');
  c.innerHTML = '<h2>News</h2><h3>Freundschaftsanfragen</h3>';
  nw.friend_requests.forEach(r => {
    c.innerHTML += `
      <div>
        Von: ${r.from_user_id}
        <button onclick="respondFR(${r.id},'accepted',${r.from_user_id})">Annehmen</button>
        <button onclick="respondFR(${r.id},'rejected',0)">Ablehnen</button>
      </div>`;
  });
  c.innerHTML += '<h3>Challenges</h3>';
  nw.challenges.forEach(ch => {
    c.innerHTML += `
      <div>
        Von: ${ch.from_user_id}
        <button onclick="respondCH(${ch.id},'accept')">Annehmen</button>
        <button onclick="respondCH(${ch.id},'decline')">Ablehnen</button>
      </div>`;
  });
}
async function respondFR(rid, dec, from) {
  await API('friend', 'respond', { request_id: rid, decision: dec, from_user: from });
  loadView('news');
}
async function respondCH(cid, dec) {
  await API('challenge', dec, { challenge_id: cid });
  loadView('news');
}

//
// ARENA
//
async function renderArena() {
  const c = document.getElementById('view-container');
  c.innerHTML = '<h2>Arena</h2>';
  const inv = await API('pets', 'inventory');
  if (!inv.length) return (c.innerHTML += '<p>Keine Pets.</p>');
  c.innerHTML += `
    <select id="petA">${inv.map(p => `<option value="${p.id}">${p.name}</option>`).join('')}</select>
    <select id="petB">${inv.map(p => `<option value="${p.id}">${p.name}</option>`).join('')}</select>
    <button onclick="startFight()">Testkampf</button>
    <button onclick="sendChallenge()">Challenge</button>
    <div id="arena-result"></div>`;
}
async function startFight() {
  const a = document.getElementById('petA').value;
  const b = document.getElementById('petB').value;
  const form = new URLSearchParams();
  form.append('petA', JSON.stringify({ id: a, attack: 5, defense: 5 }));
  form.append('petB', JSON.stringify({ id: b, attack: 5, defense: 5 }));
  const res = await fetch('php/fight.php', { method: 'POST', body: form }).then(r => r.json());
  document.getElementById('arena-result').innerText = 'Gewinner: ' + res.winner;
}
async function sendChallenge() {
  const to = prompt('Gegner-ID?');
  const a = document.getElementById('petA').value;
  const b = document.getElementById('petB').value;
  await API('challenge', 'send', { to_user: to, petA: a, petB: b });
  alert('Challenge gesendet');
}

//
// CHAT
//
async function renderChatList() {
  const list = await API('friend', 'list');
  const c = document.getElementById('view-container');
  c.innerHTML = '<h2>Chat</h2>';
  list.forEach(f => {
    if (!f.blocked) c.innerHTML += `<div onclick="openChat(${f.friend_id})">Chat mit ${f.friend_id}</div>`;
  });
}
function openChat(fid) {
  const c = document.getElementById('view-container');
  c.innerHTML = `
    <h2>Chat mit ${fid}</h2>
    <div id="chat-box" style="height:300px;overflow:auto;"></div>
    <input id="chat-msg" placeholder="Nachricht"><button onclick="sendMsg(${fid})">Senden</button>`;
  fetchChat(fid);
  setInterval(() => fetchChat(fid), 2000);
}
async function fetchChat(fid) {
  const msgs = await API('chat', 'fetch');
  const box = document.getElementById('chat-box');
  if (!msgs) return;
  box.innerHTML = msgs
    .filter(m => m.from_user_id == fid || m.to_user_id == fid)
    .map(m => `<div><b>${m.from_user_id == fid ? fid : 'Ich'}:</b> ${m.content}</div>`)
    .join('');
  box.scrollTop = box.scrollHeight;
}
async function sendMsg(fid) {
  const txt = document.getElementById('chat-msg').value.trim();
  if (!txt) return;
  await API('chat', 'send', { to_user: fid, content: txt });
  document.getElementById('chat-msg').value = '';
  fetchChat(fid);
}