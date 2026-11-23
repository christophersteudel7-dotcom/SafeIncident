// ==========================================================
// Funções compartilhadas por todas as telas internas.
// REGRA DE SEGURANÇA (XSS): dados vindos da API entram na tela
// com textContent / createElement, nunca com innerHTML.
// ==========================================================

// ---------- Comunicação com a API ----------

let voltandoAoLogin = false;

function voltarAoLogin() {
  if (voltandoAoLogin) return;
  voltandoAoLogin = true;
  document.body.classList.add('carregando');
  window.location.replace('login.html');
}

// Chama um endpoint e devolve o JSON. Se a sessão expirou (401), volta ao login.
async function chamarApi(url, opcoes = {}) {
  // Todas as escritas das telas internas passam aqui, inclusive o logout.
  if ((opcoes.method || 'GET').toUpperCase() === 'POST') {
    const headers = new Headers(opcoes.headers);
    headers.set('Content-Type', 'application/json');
    headers.set('X-SafeIncident', '1');
    opcoes = { ...opcoes, headers };
  }
  const resposta = await fetch(url, opcoes);

  if (resposta.status === 401) {
    voltarAoLogin();
    throw new Error('Sessão expirada');
  }

  let dados = null;
  try { dados = await resposta.json(); } catch (e) { /* resposta sem JSON */ }

  if (!resposta.ok) {
    throw new Error((dados && dados.erro) || 'Não foi possível concluir a operação');
  }
  return dados;
}

// Envia um objeto como JSON (POST)
function enviarJson(url, corpo) {
  return chamarApi(url, {
    method: 'POST',
    body: JSON.stringify(corpo)
  });
}

// ---------- Criação de elementos ----------

function el(tag, classe, texto) {
  const e = document.createElement(tag);
  if (classe) e.className = classe;
  if (texto !== undefined && texto !== null) e.textContent = texto;
  return e;
}

function limpar(elemento) {
  while (elemento.firstChild) elemento.removeChild(elemento.firstChild);
}

// ---------- Rótulos e badges ----------

// No banco os valores não têm acento; na tela mostramos com acento
const ROTULOS = { Media: 'Média', Critica: 'Crítica' };
function rotulo(valor) { return ROTULOS[valor] || valor; }

const CLASSE_PRIORIDADE = { Baixa: 'badge-baixa', Media: 'badge-media', Alta: 'badge-alta', Critica: 'badge-critica' };
const CLASSE_STATUS = { 'Pendente': 'badge-pendente', 'Em andamento': 'badge-andamento', 'Resolvido': 'badge-resolvido' };

function badgePrioridade(valor) { return el('span', 'badge ' + (CLASSE_PRIORIDADE[valor] || ''), rotulo(valor)); }
function badgeStatus(valor) { return el('span', 'badge ' + (CLASSE_STATUS[valor] || ''), valor); }

// Cores dos gráficos (mesmas dos badges)
const COR = {
  baixa: '#8B98A9', media: '#D8A83B', alta: '#E0823D', critica: '#D8555A',
  pendente: '#D8A83B', andamento: '#4C8DD6', resolvido: '#2FA97A'
};
const CORES_PRIORIDADE = [COR.baixa, COR.media, COR.alta, COR.critica];
const CORES_STATUS = [COR.pendente, COR.andamento, COR.resolvido];
const PALETA = ['#2FA97A', '#4C8DD6', '#D8A83B', '#E0823D', '#D8555A', '#8B98A9', '#7C6BC4'];

// ---------- Datas e números ----------

function formatarData(valor) {
  if (!valor) return '—';
  const partes = valor.substring(0, 10).split('-');
  return partes[2] + '/' + partes[1] + '/' + partes[0];
}

function formatarDataHora(valor) {
  if (!valor) return '—';
  return formatarData(valor) + ' às ' + valor.substring(11, 16);
}

// Menos de 48 horas aparece em horas; acima disso, em dias
function formatarTempo(horas) {
  if (horas === null || horas === undefined) return '—';
  const h = Number(horas);
  if (h < 48) return h.toFixed(1).replace('.', ',') + ' h';
  return (h / 24).toFixed(1).replace('.', ',') + ' dias';
}

function dataDeHoje() {
  const hoje = new Date();
  const mes = String(hoje.getMonth() + 1).padStart(2, '0');
  const dia = String(hoje.getDate()).padStart(2, '0');
  return hoje.getFullYear() + '-' + mes + '-' + dia; // Formato do input date no fuso local.
}

// ---------- Avisos (toast) e confirmação ----------

function mostrarToast(mensagem, tipo) {
  let area = document.getElementById('area-toasts');
  if (!area) {
    area = el('div');
    area.id = 'area-toasts';
    document.body.appendChild(area);
  }
  const toast = el('div', 'toast ' + (tipo === 'erro' ? 'erro' : ''), mensagem);
  area.appendChild(toast);
  setTimeout(() => toast.remove(), 4000);
}

// Para mostrar um aviso na PRÓXIMA página (ex.: depois de salvar e redirecionar)
function avisarNaProximaPagina(mensagem) { sessionStorage.setItem('toast', mensagem); }
(function () {
  const guardado = sessionStorage.getItem('toast');
  if (guardado) {
    sessionStorage.removeItem('toast');
    mostrarToast(guardado);
  }
})();

// Janela de confirmação (devolve uma Promise: true se confirmou, false se cancelou)
function confirmarAcao(mensagem, textoBotao) {
  return new Promise((resolver) => {
    const fundo = el('div', 'modal-fundo');
    const modal = el('div', 'modal');
    modal.setAttribute('role', 'dialog');
    modal.setAttribute('aria-modal', 'true');
    modal.appendChild(el('p', null, mensagem));

    const acoes = el('div', 'acoes-modal');
    const cancelar = el('button', 'btn btn-secundario', 'Cancelar');
    const confirmar = el('button', 'btn btn-perigo', textoBotao || 'Confirmar');
    acoes.appendChild(cancelar);
    acoes.appendChild(confirmar);
    modal.appendChild(acoes);
    fundo.appendChild(modal);
    document.body.appendChild(fundo);
    cancelar.focus();

    function fechar(resultado) {
      document.removeEventListener('keydown', aoTeclar);
      fundo.remove();
      resolver(resultado);
    }
    function aoTeclar(e) { if (e.key === 'Escape') fechar(false); }
    document.addEventListener('keydown', aoTeclar);
    cancelar.addEventListener('click', () => fechar(false));
    confirmar.addEventListener('click', () => fechar(true));
    fundo.addEventListener('click', (e) => { if (e.target === fundo) fechar(false); });
  });
}

// ---------- Gráficos (Chart.js) ----------

const graficos = {}; // guarda os gráficos já criados para poder refazê-los

// tipo: 'bar' ou 'doughnut'. horizontal só vale para barras.
function desenharGrafico(idCanvas, tipo, rotulos, valores, cores, horizontal) {
  const canvas = document.getElementById(idCanvas);
  const area = canvas.parentElement;
  let aviso = document.getElementById(idCanvas + '-alternativa');

  function mostrarValores() {
    area.hidden = true;
    if (!aviso) {
      aviso = el('p', 'texto-suave');
      aviso.id = idCanvas + '-alternativa';
      area.after(aviso);
    }
    const itens = rotulos.map((rotulo, i) => rotulo + ': ' + valores[i]);
    aviso.textContent = 'Gráfico indisponível. ' + (itens.join(' · ') || 'Nenhum dado.');
  }

  if (typeof Chart !== 'function') {
    mostrarValores();
    return;
  }

  try {
    area.hidden = false;
    if (graficos[idCanvas]) graficos[idCanvas].destroy();

    Chart.defaults.color = '#8B98A9';
    Chart.defaults.borderColor = '#243041';
    Chart.defaults.font.family = 'Inter, system-ui, sans-serif';

    const opcoes = {
      responsive: true,
      maintainAspectRatio: false,
      plugins: { legend: { display: tipo === 'doughnut', position: 'bottom' } }
    };

    if (tipo === 'bar') {
      const eixoValores = { beginAtZero: true, ticks: { precision: 0 } };
      const eixoCategorias = { grid: { display: false } };
      opcoes.indexAxis = horizontal ? 'y' : 'x';
      opcoes.scales = horizontal ? { x: eixoValores, y: eixoCategorias } : { x: eixoCategorias, y: eixoValores };
    }

    graficos[idCanvas] = new Chart(canvas, {
      type: tipo,
      data: {
        labels: rotulos,
        datasets: [{
          data: valores,
          backgroundColor: cores,
          borderWidth: 0,
          borderRadius: tipo === 'bar' ? 4 : 0
        }]
      },
      options: opcoes
    });
    if (aviso) aviso.remove();
  } catch (erro) {
    // Se o navegador falhar ao desenhar, os demais dados da página continuam disponíveis.
    mostrarValores();
  }
}

// ---------- Estados vazios ----------

function criarEstado(titulo, texto, classeExtra) {
  const e = el('div', 'estado ' + (classeExtra || ''));
  e.appendChild(el('strong', null, titulo));
  if (texto) e.appendChild(el('span', null, texto));
  return e;
}
