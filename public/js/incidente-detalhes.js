// Detalhes de um incidente + alteração de status + exclusão.

const idIncidente = new URLSearchParams(window.location.search).get('id');
let incidenteAtual = null;

function trocar(idElemento, novoNo) {
  const alvo = document.getElementById(idElemento);
  limpar(alvo);
  alvo.appendChild(novoNo);
}

function mostrarErro(mensagem) {
  document.getElementById('carregando').hidden = true;
  document.getElementById('conteudo-detalhes').hidden = true;
  document.getElementById('acoes-topo').hidden = true;
  const caixa = document.getElementById('erro');
  limpar(caixa);
  const estado = criarEstado('Incidente não encontrado', mensagem, 'estado-erro');
  const voltar = el('a', 'btn btn-secundario', 'Voltar para incidentes');
  voltar.href = 'incidentes.html';
  estado.appendChild(document.createElement('br'));
  estado.appendChild(voltar);
  caixa.appendChild(estado);
  caixa.hidden = false;
}

async function carregar() {
  if (!idIncidente) {
    mostrarErro('Nenhum incidente foi informado.');
    return;
  }

  try {
    const i = await chamarApi('../api/incidentes/detalhes.php?id=' + encodeURIComponent(idIncidente));
    incidenteAtual = i;

    document.getElementById('det-titulo').textContent = i.titulo;
    trocar('det-prioridade', badgePrioridade(i.prioridade));
    trocar('det-status', badgeStatus(i.status));
    document.getElementById('det-tipo').textContent = i.tipo;
    document.getElementById('det-sistema').textContent = i.sistema_afetado;
    document.getElementById('det-ocorrencia').textContent = formatarData(i.data_ocorrencia);
    document.getElementById('det-registrado').textContent = i.registrado_por;
    document.getElementById('det-criacao').textContent = formatarDataHora(i.data_criacao);
    document.getElementById('det-resolucao').textContent = formatarDataHora(i.data_resolucao);
    document.getElementById('det-descricao').textContent = i.descricao;
    document.getElementById('novo-status').value = i.status;
    document.getElementById('btn-editar').href = 'incidente-form.html?id=' + encodeURIComponent(i.id_incidente);

    document.getElementById('carregando').hidden = true;
    document.getElementById('erro').hidden = true;
    document.getElementById('conteudo-detalhes').hidden = false;
    document.getElementById('acoes-topo').hidden = false;
  } catch (erro) {
    mostrarErro(erro.message);
  }
}

document.getElementById('btn-status').addEventListener('click', async () => {
  const botao = document.getElementById('btn-status');
  botao.disabled = true;
  try {
    await enviarJson('../api/incidentes/atualizar-status.php', {
      id_incidente: incidenteAtual.id_incidente,
      status: document.getElementById('novo-status').value
    });
    mostrarToast('Status atualizado');
    await carregar();
  } catch (erro) {
    mostrarToast(erro.message, 'erro');
  } finally {
    botao.disabled = false;
  }
});

document.getElementById('btn-excluir').addEventListener('click', async () => {
  const confirmou = await confirmarAcao(
    'Excluir o incidente "' + incidenteAtual.titulo + '"? Ele deixará de aparecer no sistema.',
    'Excluir'
  );
  if (!confirmou) return;

  try {
    await enviarJson('../api/incidentes/excluir.php', { id_incidente: incidenteAtual.id_incidente });
    avisarNaProximaPagina('Incidente excluído');
    window.location.href = 'incidentes.html';
  } catch (erro) {
    mostrarToast(erro.message, 'erro');
  }
});

carregar();
