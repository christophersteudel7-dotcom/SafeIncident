// Lista de incidentes: busca, filtros, paginação e ações por linha.

const campoBusca = document.getElementById('busca');
const filtroTipo = document.getElementById('filtro-tipo');
const filtroPrioridade = document.getElementById('filtro-prioridade');
const filtroStatus = document.getElementById('filtro-status');
const corpoTabela = document.getElementById('corpo-tabela');

let paginaAtual = 1;
let totalPaginas = 1;
let numeroDaBusca = 0; // evita que uma resposta antiga sobrescreva uma mais nova

// Mostra apenas uma das áreas: 'carregando', 'erro', 'vazio' ou 'tabela'
function mostrarArea(nome) {
  document.getElementById('carregando').hidden = nome !== 'carregando';
  document.getElementById('erro').hidden = nome !== 'erro';
  document.getElementById('vazio').hidden = nome !== 'vazio';
  document.getElementById('area-tabela').hidden = nome !== 'tabela';
}

async function carregarTipos() {
  try {
    const tipos = await chamarApi('../api/incidentes/tipos.php');
    tipos.forEach(t => {
      const opcao = el('option', null, t.nome);
      opcao.value = t.id_tipo;
      filtroTipo.appendChild(opcao);
    });
  } catch (erro) {
    mostrarToast(erro.message, 'erro');
  }
}

async function buscar() {
  const minhaBusca = ++numeroDaBusca;
  mostrarArea('carregando');

  const params = new URLSearchParams({ pagina: paginaAtual, por_pagina: 10 });
  if (campoBusca.value.trim()) params.set('q', campoBusca.value.trim());
  if (filtroTipo.value) params.set('id_tipo', filtroTipo.value);
  if (filtroPrioridade.value) params.set('prioridade', filtroPrioridade.value);
  if (filtroStatus.value) params.set('status', filtroStatus.value);

  try {
    const r = await chamarApi('../api/incidentes/listar.php?' + params.toString());
    if (minhaBusca !== numeroDaBusca) return; // chegou uma busca mais nova, ignora esta

    paginaAtual = r.pagina;
    totalPaginas = r.total_paginas;

    if (r.total === 0) {
      const caixa = document.getElementById('vazio');
      limpar(caixa);
      caixa.appendChild(criarEstado('Nenhum incidente encontrado', 'Ajuste a busca ou limpe os filtros.'));
      mostrarArea('vazio');
      return;
    }

    desenharTabela(r.dados);
    document.getElementById('info-paginacao').textContent =
      'Página ' + r.pagina + ' de ' + r.total_paginas + ' · ' + r.total + ' incidente(s)';
    document.getElementById('btn-anterior').disabled = r.pagina <= 1;
    document.getElementById('btn-proxima').disabled = r.pagina >= r.total_paginas;
    mostrarArea('tabela');
  } catch (erro) {
    if (minhaBusca !== numeroDaBusca) return;
    const caixa = document.getElementById('erro');
    limpar(caixa);
    caixa.appendChild(criarEstado('Não foi possível carregar os incidentes', erro.message, 'estado-erro'));
    mostrarArea('erro');
  }
}

function desenharTabela(lista) {
  limpar(corpoTabela);
  lista.forEach(i => corpoTabela.appendChild(criarLinha(i)));
}

function criarLinha(i) {
  const tr = el('tr');

  const tdTitulo = el('td');
  const link = el('a', 'titulo-link', i.titulo);
  link.href = 'incidente-detalhes.html?id=' + encodeURIComponent(i.id_incidente);
  tdTitulo.appendChild(link);
  tr.appendChild(tdTitulo);

  tr.appendChild(el('td', null, i.tipo));
  tr.appendChild(el('td', null, i.sistema_afetado));

  const tdPrioridade = el('td');
  tdPrioridade.appendChild(badgePrioridade(i.prioridade));
  tr.appendChild(tdPrioridade);

  const tdStatus = el('td');
  tdStatus.appendChild(badgeStatus(i.status));
  tr.appendChild(tdStatus);

  tr.appendChild(el('td', null, formatarData(i.data_ocorrencia)));

  // Ações: ver, editar e excluir
  const tdAcoes = el('td');
  const acoes = el('div', 'acoes-linha');

  const ver = el('a', 'btn btn-secundario btn-pequeno', 'Ver');
  ver.href = 'incidente-detalhes.html?id=' + encodeURIComponent(i.id_incidente);
  const editar = el('a', 'btn btn-secundario btn-pequeno', 'Editar');
  editar.href = 'incidente-form.html?id=' + encodeURIComponent(i.id_incidente);
  const excluir = el('button', 'btn btn-perigo btn-pequeno', 'Excluir');
  excluir.type = 'button';
  excluir.addEventListener('click', () => excluirIncidente(i));

  acoes.appendChild(ver);
  acoes.appendChild(editar);
  acoes.appendChild(excluir);
  tdAcoes.appendChild(acoes);
  tr.appendChild(tdAcoes);

  return tr;
}

async function excluirIncidente(incidente) {
  const confirmou = await confirmarAcao(
    'Excluir o incidente "' + incidente.titulo + '"? Ele deixará de aparecer no sistema.',
    'Excluir'
  );
  if (!confirmou) return;

  try {
    await enviarJson('../api/incidentes/excluir.php', { id_incidente: incidente.id_incidente });
    mostrarToast('Incidente excluído');
    buscar();
  } catch (erro) {
    mostrarToast(erro.message, 'erro');
  }
}

// ---------- Eventos ----------

// Busca com atraso de 400 ms: só consulta quando o usuário para de digitar
let temporizador;
campoBusca.addEventListener('input', () => {
  clearTimeout(temporizador);
  temporizador = setTimeout(() => { paginaAtual = 1; buscar(); }, 400);
});

[filtroTipo, filtroPrioridade, filtroStatus].forEach(campo => {
  campo.addEventListener('change', () => { paginaAtual = 1; buscar(); });
});

document.getElementById('btn-limpar').addEventListener('click', () => {
  campoBusca.value = '';
  filtroTipo.value = '';
  filtroPrioridade.value = '';
  filtroStatus.value = '';
  paginaAtual = 1;
  buscar();
});

document.getElementById('btn-anterior').addEventListener('click', () => {
  if (paginaAtual > 1) { paginaAtual--; buscar(); }
});
document.getElementById('btn-proxima').addEventListener('click', () => {
  if (paginaAtual < totalPaginas) { paginaAtual++; buscar(); }
});

carregarTipos();
buscar();
