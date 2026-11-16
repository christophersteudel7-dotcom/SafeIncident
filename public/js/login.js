const form = document.getElementById('form-login');
const msgErro = document.getElementById('mensagem-erro');
const btn = document.getElementById('btn-entrar');

form.addEventListener('submit', async (evento) => {
  evento.preventDefault(); // impede o recarregamento padrão da página
  msgErro.hidden = true;

  const email = document.getElementById('email').value.trim();
  const senha = document.getElementById('senha').value;

  // Validação no frontend (melhora a experiência; a validação real é no PHP)
  if (!email || !senha) {
    mostrarErro('Preencha e-mail e senha.');
    return;
  }

  btn.disabled = true;
  btn.textContent = 'Entrando...';

  try {
    const resposta = await fetch('../api/auth/login.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ email, senha })
    });
    const dados = await resposta.json();

    if (resposta.ok) {
      window.location.href = 'dashboard.html';
    } else {
      mostrarErro(dados.erro || 'Erro ao fazer login.');
    }
  } catch (erro) {
    mostrarErro('Não foi possível conectar ao servidor.');
  } finally {
    btn.disabled = false;
    btn.textContent = 'Entrar';
  }
});

function mostrarErro(texto) {
  msgErro.textContent = texto; // textContent (e não innerHTML) evita injeção de HTML/XSS
  msgErro.hidden = false;
}