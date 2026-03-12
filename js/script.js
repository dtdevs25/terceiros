// Funções de validação e interatividade para Gestão de Terceiros

document.addEventListener("DOMContentLoaded", function () {

    // Validação do formulário de Empresas (admin/empresas.php)
    const empresaForm = document.getElementById("empresa-form");
    if (empresaForm) {
        empresaForm.addEventListener("submit", function(event) {
            let isValid = true;
            let errorMessages = [];
            const errorDivId = "empresa-form-errors"; // ID para a div de erros

            // Limpar erros anteriores
            clearFormErrors(empresaForm, errorDivId);

            // Validar Nome da Empresa
            const nomeEmpresa = document.getElementById("nome");
            if (nomeEmpresa && nomeEmpresa.value.trim() === "") {
                isValid = false;
                nomeEmpresa.classList.add("is-invalid");
                errorMessages.push("O Nome da Empresa é obrigatório.");
            }

            // Validar CNPJ (formato básico)
            const cnpj = document.getElementById("cnpj");
            if (cnpj && cnpj.value.trim() !== "" && !/\d{2}\.\d{3}\.\d{3}\/\d{4}-\d{2}/.test(cnpj.value)) {
                 // Apenas valida se preenchido, não obriga formato estrito aqui
                 // Poderia adicionar máscara ou validação mais robusta
            }

            // Validar CEP (formato básico)
            const cep = document.getElementById("cep");
            if (cep && cep.value.trim() !== "" && !/\d{5}-\d{3}/.test(cep.value)) {
                 // Apenas valida se preenchido
            }

            // Validar Email de Contato
            const emailContato = document.getElementById("email_contato");
            if (emailContato && emailContato.value.trim() !== "" && !/^[\w-\.]+@([\w-]+\.)+[\w-]{2,4}$/.test(emailContato.value)) {
                isValid = false;
                emailContato.classList.add("is-invalid");
                errorMessages.push("O Email de Contato fornecido não é válido.");
            }

            // Exibir erros se houver
            if (!isValid) {
                event.preventDefault();
                displayFormErrors(empresaForm, errorDivId, errorMessages);
            }
        });
    }

    // Validação do formulário de Filiais (admin/filiais.php) - Adicionar ID="filial-form" no form
    const filialForm = document.getElementById("filial-form");
    if (filialForm) {
        filialForm.addEventListener("submit", function(event) {
            let isValid = true;
            let errorMessages = [];
            const errorDivId = "filial-form-errors";

            clearFormErrors(filialForm, errorDivId);

            // Validar Nome da Filial
            const nomeFilial = document.getElementById("nome");
            if (nomeFilial && nomeFilial.value.trim() === "") {
                isValid = false;
                nomeFilial.classList.add("is-invalid");
                errorMessages.push("O Nome da Filial é obrigatório.");
            }

            if (!isValid) {
                event.preventDefault();
                displayFormErrors(filialForm, errorDivId, errorMessages);
            }
        });
    }

    // Validação do formulário de Usuários (admin/usuarios.php) - Adicionar ID="usuario-form" no form
    const usuarioForm = document.getElementById("usuario-form");
    if (usuarioForm) {
        usuarioForm.addEventListener("submit", function(event) {
            let isValid = true;
            let errorMessages = [];
            const errorDivId = "usuario-form-errors";

            clearFormErrors(usuarioForm, errorDivId);

            // Validar Nome
            const nomeUsuario = document.getElementById("nome");
            if (nomeUsuario && nomeUsuario.value.trim() === "") {
                isValid = false;
                nomeUsuario.classList.add("is-invalid");
                errorMessages.push("O Nome do Usuário é obrigatório.");
            }

            // Validar Email
            const emailUsuario = document.getElementById("email");
            if (emailUsuario && (emailUsuario.value.trim() === "" || !/^[\w-\.]+@([\w-]+\.)+[\w-]{2,4}$/.test(emailUsuario.value))) {
                isValid = false;
                emailUsuario.classList.add("is-invalid");
                errorMessages.push("O Email fornecido não é válido ou está vazio.");
            }

            // Validar Senha (apenas se for cadastro ou se a senha for alterada)
            const senhaUsuario = document.getElementById("senha");
            const confirmaSenhaUsuario = document.getElementById("confirma_senha");
            const isEdit = document.querySelector("input[name=\"user_id\"]") !== null;

            if (senhaUsuario && confirmaSenhaUsuario) {
                const senhaVal = senhaUsuario.value;
                const confirmaSenhaVal = confirmaSenhaUsuario.value;

                // Senha é obrigatória no cadastro
                if (!isEdit && senhaVal === "") {
                    isValid = false;
                    senhaUsuario.classList.add("is-invalid");
                    errorMessages.push("A Senha é obrigatória para novos usuários.");
                }
                // Se a senha foi digitada (cadastro ou edição), verificar confirmação e força
                if (senhaVal !== "") {
                    if (senhaVal.length < 6) {
                         isValid = false;
                         senhaUsuario.classList.add("is-invalid");
                         errorMessages.push("A Senha deve ter pelo menos 6 caracteres.");
                    }
                    if (senhaVal !== confirmaSenhaVal) {
                        isValid = false;
                        senhaUsuario.classList.add("is-invalid");
                        confirmaSenhaUsuario.classList.add("is-invalid");
                        errorMessages.push("As senhas não coincidem.");
                    }
                }
            }

            // Validar Tipo de Usuário
            const tipoUsuario = document.getElementById("tipo");
            if (tipoUsuario && tipoUsuario.value === "") {
                isValid = false;
                tipoUsuario.classList.add("is-invalid");
                errorMessages.push("O Tipo de Usuário é obrigatório.");
            }

            if (!isValid) {
                event.preventDefault();
                displayFormErrors(usuarioForm, errorDivId, errorMessages);
            }
        });
    }

    // Adicionar confirmação visual para exclusão (usando Bootstrap Modal)
    const deleteForms = document.querySelectorAll("form[onsubmit*=\"confirm(\"]");
    deleteForms.forEach(form => {
        form.addEventListener("submit", function(event) {
            event.preventDefault(); // Previne o envio padrão
            const confirmationMessage = form.getAttribute("onsubmit").match(/confirm\(\"(.*?)\"\)/)[1];

            // Cria ou obtém o modal
            let confirmationModal = document.getElementById("confirmationModal");
            if (!confirmationModal) {
                confirmationModal = document.createElement("div");
                confirmationModal.innerHTML = `
                    <div class="modal fade" id="confirmationModal" tabindex="-1" aria-labelledby="confirmationModalLabel" aria-hidden="true">
                      <div class="modal-dialog">
                        <div class="modal-content">
                          <div class="modal-header">
                            <h5 class="modal-title" id="confirmationModalLabel">Confirmação</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                          </div>
                          <div class="modal-body">
                            ${confirmationMessage}
                          </div>
                          <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="button" class="btn btn-danger" id="confirmDeleteBtn">Confirmar Exclusão</button>
                          </div>
                        </div>
                      </div>
                    </div>
                `;
                document.body.appendChild(confirmationModal);
            } else {
                // Atualiza a mensagem se o modal já existe
                confirmationModal.querySelector(".modal-body").innerText = confirmationMessage;
            }

            const modalInstance = new bootstrap.Modal(confirmationModal);
            const confirmBtn = confirmationModal.querySelector("#confirmDeleteBtn");

            // Remove listener antigo para evitar múltiplos envios
            const newConfirmBtn = confirmBtn.cloneNode(true);
            confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);

            newConfirmBtn.addEventListener("click", () => {
                form.submit(); // Envia o formulário original
            });

            modalInstance.show();
        });
        // Remove o onsubmit inline para usar o modal
        form.removeAttribute("onsubmit");
    });

    // Função para visualizar logs em popup
    const viewLogButtons = document.querySelectorAll(".view-log-btn");
    if (viewLogButtons.length > 0) {
        viewLogButtons.forEach(button => {
            button.addEventListener("click", function(e) {
                e.preventDefault();
                const logPath = this.getAttribute("data-log-path");
                if (logPath) {
                    fetch(`view_log.php?file=${encodeURIComponent(logPath)}`)
                        .then(response => response.json())
                        .then(data => {
                            const logModal = document.getElementById("viewLogModal");
                            const logContent = document.getElementById("logContent");
                            const logModalTitle = document.getElementById("viewLogModalLabel");
                            
                            if (data.success) {
                                if (data.type === 'text') {
                                    // Para arquivos de texto, exibir o conteúdo
                                    logContent.innerHTML = `<pre class="log-content">${escapeHtml(data.content)}</pre>`;
                                    logModalTitle.textContent = "Visualizar Log";
                                } else if (data.type === 'pdf') {
                                    // Para PDFs, usar iframe
                                    logContent.innerHTML = `<iframe src="${data.path}" style="width: 100%; height: 500px; border: none;"></iframe>`;
                                    logModalTitle.textContent = "Visualizar PDF";
                                } else if (['png', 'jpg', 'jpeg', 'gif'].includes(data.type)) {
                                    // Para imagens, exibir a imagem
                                    logContent.innerHTML = `<img src="${data.path}" class="img-fluid" alt="Log Image">`;
                                    logModalTitle.textContent = "Visualizar Imagem";
                                } else {
                                    // Para outros tipos não suportados
                                    logContent.innerHTML = `<div class="alert alert-warning">Este tipo de arquivo não pode ser visualizado diretamente. Use o botão de download.</div>`;
                                    logModalTitle.textContent = "Arquivo não visualizável";
                                }
                            } else {
                                // Em caso de erro
                                logContent.innerHTML = `<div class="alert alert-danger">${data.error || 'Erro ao carregar o arquivo'}</div>`;
                                logModalTitle.textContent = "Erro";
                            }
                            
                            const modal = new bootstrap.Modal(logModal);
                            modal.show();
                        })
                        .catch(error => {
                            console.error("Erro ao carregar o log:", error);
                            alert("Erro ao carregar o arquivo de log. Por favor, tente novamente.");
                        });
                }
            });
        });
    }

    // Inicializar todos os dropdowns do Bootstrap
    const dropdownElementList = document.querySelectorAll('.dropdown-toggle');
    if (dropdownElementList.length > 0) {
        dropdownElementList.forEach(function (dropdownToggleEl) {
            new bootstrap.Dropdown(dropdownToggleEl);
        });
    }
});

// Função auxiliar para limpar erros do formulário
function clearFormErrors(formElement, errorDivId) {
    const errorDiv = formElement.querySelector(`#${errorDivId}`); // Busca dentro do form
    if (errorDiv) {
        errorDiv.innerHTML = "";
        errorDiv.style.display = "none";
    }
    formElement.querySelectorAll(".is-invalid").forEach(el => el.classList.remove("is-invalid"));
}

// Função auxiliar para exibir erros do formulário
function displayFormErrors(formElement, errorDivId, errorMessages) {
    let errorHtml = "<strong>Por favor, corrija os seguintes erros:</strong><ul>";
    errorMessages.forEach(msg => {
        errorHtml += `<li>${msg}</li>`;
    });
    errorHtml += "</ul>";

    let errorDiv = formElement.querySelector(`#${errorDivId}`);
    if (!errorDiv) {
        errorDiv = document.createElement("div");
        errorDiv.id = errorDivId;
        errorDiv.className = "alert alert-danger mt-3";
        // Insere a div de erros antes do primeiro elemento filho do formulário
        formElement.insertBefore(errorDiv, formElement.firstChild);
    }
    errorDiv.innerHTML = errorHtml;
    errorDiv.style.display = "block";
    // Rola para o topo do formulário para ver os erros
    errorDiv.scrollIntoView({ behavior: "smooth", block: "start" });
}

// Função para escapar HTML e prevenir XSS
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
