// Formulário usado para CADASTRAR (sem ?id=) e para EDITAR (com ?id=).

const idEdicao = new URLSearchParams(window.location.search).get('id');
const editando = idEdicao !== null;
let idConfirmado = null; // Preenchido quando a API encontra o incidente para edição.

const form = document.getElementById('form-incidente');
const botaoSalvar = document.getElementById('btn-salvar');
const CAMPOS = ['titulo', 'id_tipo', 'prioridade', 'sistema_afetado', 'data_ocorrencia', 'descricao'];

// Data da ocorrência não pode ser no futuro
document.getElementById('data_ocorrencia').min = '1000-01-01';
document.getElementById('data_ocorrencia').max = dataDeHoje();

// ---------- Validação no navegador (a validação de verdade é refeita no PHP) ----------

function lerCampos() {
  const dados = {};
  CAMPOS.forEach(c => { dados[c] = document.getElementById(c).value.trim(); });
  return dados;
}

function validar(d) {
  const erros = {};
  if (d.titulo.length < 3 || d.titulo.length > 150) erros.titulo = 'Informe um título de 3 a 150 caracteres.';
  if (!d.id_tipo) erros.id_tipo = 'Selecione o tipo de ataque.';
  if (!d.prioridade) erros.prioridade = 'Selecione a prioridade.';
  if (d.sistema_afetado.length < 2 || d.sistema_afetado.length > 100) erros.sistema_afetado = 'Informe o sistema afetado (2 a 100 caracteres).';
  if (!d.data_ocorrencia) erros.data_ocorrencia = 'Informe a data da ocorrência.';
  else if (!/^[1-9][0-9]{3}-[0-9]{2}-[0-9]{2}$/.test(d.data_ocorrencia)) erros.data_ocorrencia = 'Informe uma data entre os anos 1000 e 9999.';
  else if (d.data_ocorrencia > dataDeHoje()) erros.data_ocorrencia = 'A data não pode ser futura.';
  if (d.descricao.length < 10) erros.descricao = 'Descreva o incidente com pelo menos 10 caracteres.';
  return erros;
}

function mostrarErros(erros) {
  CAMPOS.forEach(c => {
    const bloco = document.querySelector('[data-campo="' + c + '"]');
    const mensagem = bloco.querySelector('.erro-campo');
    if (erros[c]) {
      bloco.classList.add('com-erro');
      mensagem.textContent = erros[c];
      mensagem.hidden = false;
    } else {
      bloco.classList.remove('com-erro');
      mensagem.hidden = true;
    }
  });
}

// ---------- Carregamento inicial ----------

async function carregarTipos() {
  const tipos = await chamarApi('../api/incidentes/tipos.php');
  const select = document.getElementById('id_tipo');
  tipos.forEach(t => {
    const opcao = el('option', null, t.nome);
    opcao.value = t.id_tipo;
    select.appendChild(opcao);
  });
}

async function carregarIncidente() {
  const i = await chamarApi('../api/incidentes/detalhes.php?id=' + encodeURIComponent(idEdicao));
  idConfirmado = Number(i.id_incidente);
  document.getElementById('titulo').value = i.titulo;
  document.getElementById('id_tipo').value = i.id_tipo;
  document.getElementById('prioridade').value = i.prioridade;
  document.getElementById('sistema_afetado').value = i.sistema_afetado;
  document.getElementById('data_ocorrencia').value = i.data_ocorrencia;
  document.getElementById('descricao').value = i.descricao;
  document.getElementById('status-atual').appendChild(badgeStatus(i.status));
}

async function iniciar() {
  try {
    await carregarTipos();

    if (editando) {
      document.getElementById('titulo-pagina').textContent = 'Editar incidente';
      document.getElementById('subtitulo-pagina').textContent = 'Corrija os dados do incidente';
      document.getElementById('bloco-status').hidden = false;
      form.hidden = true;
      document.getElementById('carregando').hidden = false;
      await carregarIncidente();
      document.getElementById('carregando').hidden = true;
      form.hidden = false;
    }
  } catch (erro) {
    document.getElementById('carregando').hidden = true;
    form.hidden = true;
    const caixa = document.getElementById('erro');
    limpar(caixa);
    caixa.appendChild(criarEstado('Não foi possível abrir o formulário', erro.message, 'estado-erro'));
    const voltar = el('a', 'btn btn-secundario', 'Voltar para incidentes');
    voltar.href = 'incidentes.html';
    caixa.firstChild.appendChild(document.createElement('br'));
    caixa.firstChild.appendChild(voltar);
    caixa.hidden = false;
  }
}

// ---------- Envio ----------

form.addEventListener('submit', async (evento) => {
  evento.preventDefault();

  const dados = lerCampos();
  const erros = validar(dados);
  mostrarErros(erros);
  if (Object.keys(erros).length > 0) return;

  if (editando) {
    if (idConfirmado === null) return; // Aguarda a ficha antes de permitir salvar.
    dados.id_incidente = idConfirmado;
  }

  botaoSalvar.disabled = true;
  botaoSalvar.textContent = 'Salvando...';

  try {
    const url = editando ? '../api/incidentes/editar.php' : '../api/incidentes/criar.php';
    const r = await enviarJson(url, dados);

    avisarNaProximaPagina(editando ? 'Incidente atualizado' : 'Incidente cadastrado');
    window.location.href = editando
      ? 'incidente-detalhes.html?id=' + encodeURIComponent(idEdicao)
      : 'incidente-detalhes.html?id=' + encodeURIComponent(r.id_incidente);
  } catch (erro) {
    mostrarToast(erro.message, 'erro');
    botaoSalvar.disabled = false;
    botaoSalvar.textContent = 'Salvar incidente';
  }
});

iniciar();
