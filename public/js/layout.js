// ---------- Ícones (SVG estilo "outline", sem depender de biblioteca externa) ----------
const ICONES = {
  dashboard: '<rect width="7" height="9" x="3" y="3" rx="1"/><rect width="7" height="5" x="14" y="3" rx="1"/><rect width="7" height="9" x="14" y="12" rx="1"/><rect width="7" height="5" x="3" y="16" rx="1"/>',
  incidentes: '<path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="M12 8v4"/><path d="M12 16h.01"/>',
  relatorios: '<path d="M3 3v16a2 2 0 0 0 2 2h16"/><path d="M18 17V9"/><path d="M13 17V5"/><path d="M8 17v-3"/>',
  perfil: '<circle cx="12" cy="8" r="5"/><path d="M20 21a8 8 0 0 0-16 0"/>',
  sair: '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" x2="9" y1="12" y2="12"/>',
  recolher: '<rect width="18" height="18" x="3" y="3" rx="2"/><path d="M9 3v18"/>'
};

function icone(nome) {
  return '<svg class="icone" viewBox="0 0 24 24" fill="none" stroke="currentColor" ' +
         'stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' + ICONES[nome] + '</svg>';
}

// ---------- Itens do menu (para adicionar uma tela nova, basta incluir aqui) ----------
const ITENS_MENU = [
  { id: 'dashboard',  rotulo: 'Dashboard',  href: 'dashboard.html' },
  { id: 'incidentes', rotulo: 'Incidentes', href: 'incidentes.html' },
  { id: 'relatorios', rotulo: 'Relatórios', href: 'relatorios.html' },
  { id: 'perfil',     rotulo: 'Perfil',     href: 'perfil.html' }
];

async function iniciarLayout() {
  // 1. Só continua se houver sessão ativa no servidor
  let usuario;
  try {
    const resposta = await fetch('../api/auth/sessao.php');
    if (!resposta.ok) {
      window.location.href = 'login.html';
      return;
    }
    usuario = await resposta.json();
  } catch (erro) {
    window.location.href = 'login.html';
    return;
  }

  // 2. Lembra se o usuário tinha recolhido a sidebar
  if (localStorage.getItem('sidebarRecolhida') === '1') {
    document.body.classList.add('recolhida');
  }

  // 3. Monta a sidebar (estrutura fixa, sem dados do usuário)
  const paginaAtual = document.body.dataset.pagina;

  const links = ITENS_MENU.map(item => {
    const ativo = item.id === paginaAtual ? ' ativo' : '';
    return '<a href="' + item.href + '" class="sidebar-link' + ativo + '" title="' + item.rotulo + '">' +
             icone(item.id) + '<span class="rotulo">' + item.rotulo + '</span>' +
           '</a>';
  }).join('');

  const sidebar = document.getElementById('sidebar');
  sidebar.innerHTML =
    '<div class="sidebar-topo">' +
      '<span class="logo">' + icone('incidentes') + '<span class="logo-texto">SafeIncident</span></span>' +
      '<button id="btn-recolher" class="btn-icone" title="Recolher menu">' + icone('recolher') + '</button>' +
    '</div>' +
    '<nav class="sidebar-nav">' + links + '</nav>' +
    '<div class="sidebar-rodape">' +
      '<div class="usuario"><span class="avatar" id="avatar"></span><span class="usuario-nome" id="usuario-nome"></span></div>' +
      '<button id="btn-sair" class="sidebar-link" title="Sair">' + icone('sair') + '<span class="rotulo">Sair</span></button>' +
    '</div>';

  // 4. Dados do usuário entram com textContent (nunca innerHTML), evitando XSS
  document.getElementById('usuario-nome').textContent = usuario.nome;
  document.getElementById('avatar').textContent = usuario.nome.charAt(0).toUpperCase();

  // 5. Botão de recolher/expandir
  document.getElementById('btn-recolher').addEventListener('click', () => {
    const recolhida = document.body.classList.toggle('recolhida');
    localStorage.setItem('sidebarRecolhida', recolhida ? '1' : '0');
  });

  // 6. Botão de sair
  document.getElementById('btn-sair').addEventListener('click', async () => {
    await fetch('../api/auth/logout.php');
    window.location.href = 'login.html';
  });

  // 7. Tudo pronto: mostra a página
  document.body.classList.remove('carregando');
}

iniciarLayout();
