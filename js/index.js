
function toggleMenu(postId) {
  const menu = document.getElementById(`menu-${postId}`);
  menu.classList.toggle('hidden');

  document.querySelectorAll('.post-menu').forEach(m => {
      if (m.id !== `menu-${postId}`) {
          m.classList.add('hidden');
      }
  });
}

function initCarousel(container) {
  const items = container.querySelectorAll('[data-carousel-item]');
  const indicators = container.querySelectorAll('[data-carousel-indicator]');
  const prevBtn = container.querySelector('[data-carousel-prev]');
  const nextBtn = container.querySelector('[data-carousel-next]');

  let currentIndex = 0;
  let intervalId = null;
  const intervalDuration = 5000;

  function showItem(index) {
      currentIndex = (index + items.length) % items.length;

      items.forEach((item, i) => {
          item.classList.toggle('opacity-100', i === currentIndex);
          item.classList.toggle('opacity-0', i !== currentIndex);
      });

      if (indicators.length > 0) {
          indicators.forEach((indicator, i) => {
              indicator.classList.toggle('bg-gray-600', i === currentIndex);
              indicator.classList.toggle('bg-gray-300', i !== currentIndex);
              indicator.classList.toggle('w-4', i === currentIndex);
              indicator.classList.toggle('w-2', i !== currentIndex);
          });
      }
  }

  function next() {
      showItem(currentIndex + 1);
      resetInterval();
  }

  function prev() {
      showItem(currentIndex - 1);
      resetInterval();
  }

  function startInterval() {
      if (items.length > 1) {
          intervalId = setInterval(next, intervalDuration);
      }
  }

  function resetInterval() {
      clearInterval(intervalId);
      startInterval();
  }

  function stopInterval() {
      clearInterval(intervalId);
  }

  if (nextBtn) nextBtn.addEventListener('click', next);
  if (prevBtn) prevBtn.addEventListener('click', prev);

  if (indicators.length > 0) {
      indicators.forEach((indicator, index) => {
          indicator.addEventListener('click', () => {
              showItem(index);
              resetInterval();
          });
      });
  }

  container.addEventListener('mouseenter', stopInterval);
  container.addEventListener('mouseleave', startInterval);

  showItem(0);
  startInterval();
}

document.addEventListener('DOMContentLoaded', function() {

  document.getElementById('emoji-picker-button').addEventListener('click', function(e) {
      e.stopPropagation();
      window.emojiPicker.classList.toggle('hidden');
  });

  document.addEventListener('click', function(e) {
      if (!e.target.closest('#emoji-picker-button') && !e.target.closest('emoji-picker')) {
          window.emojiPicker.classList.add('hidden');
      }
  });

  const dropArea = document.getElementById('drop-area');
  const formPost = document.getElementById('form-post');
  const imageInput = document.getElementById('image-input');

  ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
      formPost.addEventListener(eventName, preventDefaults, false);
      dropArea.addEventListener(eventName, preventDefaults, false);
  });

  function preventDefaults(e) {
      e.preventDefault();
      e.stopPropagation();
  }

  ['dragenter', 'dragover'].forEach(eventName => {
      dropArea.addEventListener(eventName, highlight, false);
  });

  ['dragleave', 'drop'].forEach(eventName => {
      dropArea.addEventListener(eventName, unhighlight, false);
  });

  function highlight() {
      dropArea.classList.remove('hidden');
  }

  function unhighlight() {
      dropArea.classList.add('hidden');
  }

  dropArea.addEventListener('drop', handleDrop, false);

  function handleDrop(e) {
      const dt = e.dataTransfer;
      const files = dt.files;

      if (files.length > 0) {
          updateFileInput(files);
          handleFiles(files);
      }
  }

  function updateFileInput(newFiles) {
      const dataTransfer = new DataTransfer();

      if (imageInput.files) {
          for (let i = 0; i < imageInput.files.length; i++) {
              dataTransfer.items.add(imageInput.files[i]);
          }
      }

      for (let i = 0; i < newFiles.length; i++) {
          dataTransfer.items.add(newFiles[i]);
      }

      imageInput.files = dataTransfer.files;
  }

  function handleFiles(files) {
      for (let i = 0; i < files.length; i++) {
          const file = files[i];
          if (file.type.startsWith('image/')) {
              handleImageFile(file);
          } else if (file.type.startsWith('video/')) {
              handleVideoFile(file);
          } else if (file.type.startsWith('audio/')) {
              handleAudioFile(file);
          }
      }
      verificarMidia();
  }

  imageInput.addEventListener('change', function(e) {
      if (e.target.files.length > 0) {
          handleFiles(e.target.files);
      }
  });

  function handleImageFile(file) {
      const previewContainer = document.getElementById('image-previews');
      previewContainer.classList.remove('hidden');
      document.getElementById('media-previews').classList.remove('hidden');

      const reader = new FileReader();
      reader.onload = function(event) {
          const imgDiv = document.createElement('div');
          imgDiv.className = 'relative';
          imgDiv.innerHTML = `
          <img src="${event.target.result}" class="w-full h-32 object-cover rounded-lg">
          <button type="button" onclick="this.parentElement.remove(); updateFileInput([]); verificarMidia()"
              class="absolute top-1 right-1 bg-black bg-opacity-50 text-white rounded-full w-6 h-6 flex items-center justify-center">
              <i class="fas fa-times text-xs"></i>
          </button>
      `;
          document.getElementById('image-previews').appendChild(imgDiv);
      }
      reader.readAsDataURL(file);
  }

  document.getElementById('video-input').addEventListener('change', function(e) {
      if (e.target.files.length > 0) {
          handleVideoFile(e.target.files[0]);
      }
  });

  function handleVideoFile(file) {
    const videoContainer = document.getElementById('video-preview');
    const videoElement = videoContainer.querySelector('video');
    const videoSource = videoContainer.querySelector('source');
    const mediaPreviews = document.getElementById('media-previews');

    // Limpar previews de imagem se existirem
    document.querySelectorAll('#image-previews > div').forEach(el => el.remove());
    document.getElementById('image-previews').classList.add('hidden');
    
    // Limpar preview de áudio se existir
    document.getElementById('audio-preview').classList.add('hidden');
    document.getElementById('audio-source').src = '';

    // Configurar o tipo de vídeo de acordo com a extensão
    const fileExt = file.name.split('.').pop().toLowerCase();
    let videoType;
    
    switch(fileExt) {
        case 'mp4':
            videoType = 'video/mp4';
            break;
        case 'webm':
            videoType = 'video/webm';
            break;
        case 'ogg':
            videoType = 'video/ogg';
            break;
        default:
            videoType = 'video/mp4'; // padrão
    }

    // Criar URL do objeto para o vídeo
    const videoURL = URL.createObjectURL(file);
    
    // Configurar a fonte do vídeo
    videoSource.src = videoURL;
    videoSource.type = videoType;
    
    // Carregar metadados do vídeo antes de mostrar
    videoElement.load();
    
    videoElement.addEventListener('loadedmetadata', function() {
        // Ajustar a proporção do vídeo
        const aspectRatio = videoElement.videoWidth / videoElement.videoHeight;
        videoElement.style.maxHeight = '400px';
        videoElement.style.width = '100%';
        
        // Mostrar o preview
        videoContainer.classList.remove('hidden');
        mediaPreviews.classList.remove('hidden');
        
        verificarMidia();
    });
    
    videoElement.addEventListener('error', function() {
        alert('Erro ao carregar o vídeo. Formatos suportados: MP4, WebM, Ogg');
        removerVideo();
    });
}

window.removerVideo = function() {
  const videoContainer = document.getElementById('video-preview');
  const videoElement = videoContainer.querySelector('video');
  const videoSource = videoContainer.querySelector('source');
  
  // Pausar o vídeo e limpar a fonte
  videoElement.pause();
  videoElement.currentTime = 0;
  videoSource.src = '';
  
  // Revogar a URL do objeto para liberar memória
  if (videoSource.src) {
      URL.revokeObjectURL(videoSource.src);
  }
  
  videoContainer.classList.add('hidden');
  document.getElementById('video-input').value = '';
  verificarMidia();
}

  function handleAudioFile(file) {
      const audioPreview = document.getElementById('audio-preview');
      const audioSource = document.getElementById('audio-source');

      audioSource.src = URL.createObjectURL(file);
      audioPreview.classList.remove('hidden');
      document.getElementById('media-previews').classList.remove('hidden');
      verificarMidia();
  }

  window.removerAudio = function() {
      document.getElementById('audio-preview').classList.add('hidden');
      document.getElementById('audio-source').src = '';
      document.getElementById('audio-input').value = '';
      verificarMidia();
  }

  function verificarMidia() {
      const hasText = document.getElementById('post-textarea').value.trim() !== '';
      const hasImages = imageInput.files && imageInput.files.length > 0;
      const hasVideo = document.getElementById('video-input').files && document.getElementById('video-input').files.length > 0;
      const hasAudio = document.getElementById('audio-input').files && document.getElementById('audio-input').files.length > 0;

      document.getElementById('submit-button').disabled = !(hasText || hasImages || hasVideo || hasAudio);

      if (!hasImages && !hasVideo && !hasAudio) {
          document.getElementById('media-previews').classList.add('hidden');
      } else {
          document.getElementById('media-previews').classList.remove('hidden');
      }
  }

  document.getElementById('post-textarea').addEventListener('input', verificarMidia);

  document.getElementById('form-post').addEventListener('submit', function(e) {
      verificarMidia();
      if (document.getElementById('submit-button').disabled) {
          e.preventDefault();
          alert('Por favor, adicione um texto ou uma mídia antes de publicar!');
      }
  });

  document.querySelectorAll('[data-carousel-item]').forEach(item => {
      const container = item.closest('.relative.group');
      if (container && !container.dataset.carouselInitialized) {
          container.dataset.carouselInitialized = true;
          initCarousel(container);
      }
  });

  document.addEventListener('click', function(e) {
      if (!e.target.closest('[onclick^="toggleMenu"]')) {
          document.querySelectorAll('.post-menu').forEach(menu => {
              menu.classList.add('hidden');
          });
      }
  });

  verificarMidia();
});

function previewComentarioImage(input, postId) {
  const preview = document.getElementById(`preview-comentario-${postId}`);
  const file = input.files[0];

  if (file) {
      const reader = new FileReader();

      reader.onload = function(e) {
          preview.src = e.target.result;
          preview.classList.remove('hidden');
      }

      reader.readAsDataURL(file);
  } else {
      preview.src = '#';
      preview.classList.add('hidden');
  }
}

function abrirModalComentarios(postId) {
  const modal = document.getElementById('modal-comentarios');
  const post = document.querySelector(`[data-post-id="${postId}"]`);
  
  if (!post) return;
  
  // Limpar conteúdo anterior
  document.getElementById('modal-media-content').innerHTML = '';
  document.getElementById('modal-header-content').innerHTML = '';
  document.getElementById('modal-text-content').innerHTML = '';
  document.getElementById('modal-comments-list').innerHTML = '';
  
  // 1. Adicionar mídia (imagens/vídeo) ao lado esquerdo
  const mediaContainer = document.getElementById('modal-media-content');
  
  // Verificar se tem imagens
  const carouselContainer = post.querySelector('.relative.group');
  if (carouselContainer) {
      // Clonar apenas o container das imagens
      const carouselClone = carouselContainer.cloneNode(true);
      
      // Remover classes que podem interferir
      carouselClone.classList.remove('group', 'hover-lift', 'mb-4');
      
      // Ajustar estilos para o modal
      carouselClone.style.width = '100%';
      carouselClone.style.height = '100%';
      carouselClone.style.display = 'flex';
      carouselClone.style.alignItems = 'center';
      carouselClone.style.justifyContent = 'center';
      
      // Ajustar o container interno
      const innerContainer = carouselClone.querySelector('.relative.overflow-hidden');
      if (innerContainer) {
          innerContainer.style.width = '100%';
          innerContainer.style.height = '100%';
          innerContainer.style.maxHeight = '100%';
          innerContainer.style.display = 'flex';
          innerContainer.style.alignItems = 'center';
          innerContainer.style.justifyContent = 'center';
      }
      
      // Ajustar as imagens
      const images = carouselClone.querySelectorAll('[data-carousel-item]');
      images.forEach(item => {
          item.style.display = 'flex';
          item.style.alignItems = 'center';
          item.style.justifyContent = 'center';
          item.style.width = '100%';
          item.style.height = '100%';
          
          const img = item.querySelector('img');
          if (img) {
              img.style.maxHeight = '80vh';
              img.style.maxWidth = '100%';
              img.style.objectFit = 'contain';
          }
      });
      
      // Ajustar botões de navegação
      const prevBtn = carouselClone.querySelector('[data-carousel-prev]');
      const nextBtn = carouselClone.querySelector('[data-carousel-next]');
      if (prevBtn) prevBtn.style.opacity = '1';
      if (nextBtn) nextBtn.style.opacity = '1';
      
      mediaContainer.appendChild(carouselClone);
      
      // Inicializar o carrossel no clone
      initCarousel(carouselClone);
  }
  
  // 2. Adicionar cabeçalho com informações do autor
  const headerContainer = document.getElementById('modal-header-content');
  const autorInfo = post.querySelector('.flex.items-center.space-x-3').cloneNode(true);
  
  // Remover classes que podem interferir
  if (autorInfo.querySelector('img')) {
      autorInfo.querySelector('img').classList.remove('border-2');
  }
  
  headerContainer.appendChild(autorInfo);
  
  // 3. Adicionar conteúdo de texto
  const textContainer = document.getElementById('modal-text-content');
  const postContent = post.querySelector('.post-content').cloneNode(true);
  textContainer.appendChild(postContent);
  
  // 4. Atualizar o ID do post no formulário de comentários
  document.getElementById('modal-post-id').value = postId;
  
  // 5. Carregar os comentários
  carregarComentariosModal(postId);
  
  // 6. Mostrar o modal
  modal.style.display = 'flex';
  document.body.style.overflow = 'hidden';
  
  // Adicionar evento de curtir no modal
  document.getElementById('modal-curtir-btn').addEventListener('click', function() {
      const form = document.createElement('form');
      form.method = 'POST';
      
      const input = document.createElement('input');
      input.type = 'hidden';
      input.name = 'post_id';
      input.value = postId;
      
      const submit = document.createElement('button');
      submit.type = 'submit';
      submit.name = 'curtir';
      
      form.appendChild(input);
      form.appendChild(submit);
      document.body.appendChild(form);
      submit.click();
      document.body.removeChild(form);
      
      // Atualizar visualização sem recarregar
      setTimeout(() => carregarComentariosModal(postId), 300);
  });
}

function fecharModalComentarios() {
  const modal = document.getElementById('modal-comentarios');
  modal.style.display = 'none';
  document.body.style.overflow = 'auto';
}

function carregarComentariosModal(postId) {
  fetch(`carregar_comentarios.php?post_id=${postId}`)
      .then(response => {
          if (!response.ok) {
              throw new Error('Erro na requisição');
          }
          return response.json();
      })
      .then(data => {
          if (!data) {
              throw new Error('Dados inválidos');
          }
          
          let html = '';
          
          // Adicionar contagem de curtidas
          if (data.curtidas !== undefined) {
              document.getElementById('modal-likes-count').textContent = `${data.curtidas} curtidas`;
          }
          
          // Verificar se o usuário já curtiu o post
          if (data.curtido !== undefined) {
              const curtido = data.curtido ? 'text-red-600 fas' : 'far';
              document.getElementById('modal-curtir-btn').innerHTML = `<i class="${curtido} fa-heart"></i>`;
          }
          
          // Adicionar comentários
          if (data.comentarios && Array.isArray(data.comentarios)) {
              data.comentarios.forEach(comentario => {
                  html += `
                  <div class="comment-item">
                      <div class="flex items-start gap-3">
                          <div class="flex-shrink-0">
                              ${comentario.foto_perfil ? 
                                  `<img src="${comentario.foto_perfil}" class="w-10 h-10 rounded-full object-cover border border-gray-200" alt="Foto">` : 
                                  `<div class="w-10 h-10 bg-gray-200 rounded-full flex items-center justify-center">
                                      <i class="fas fa-user text-gray-400"></i>
                                  </div>`
                              }
                          </div>
                          <div class="flex-1">
                              <div class="flex items-baseline gap-2">
                                  <span class="font-semibold">${comentario.autor || 'Usuário'}</span>
                                  <span class="text-xs text-gray-500">${formatarDataModal(comentario.data_comentario)}</span>
                              </div>
                              <p class="mt-1 text-gray-800">${comentario.conteudo || ''}</p>
                              ${comentario.imagem ? 
                                  `<div class="mt-2">
                                      <img src="${comentario.imagem}" alt="Imagem do comentário" class="max-w-full h-auto max-h-40 rounded-lg border border-gray-200">
                                  </div>` : ''
                              }
                          </div>
                      </div>
                  </div>
                  `;
              });
          }
          
          if (!html) {
              html = '<p class="text-center text-gray-500 py-4">Nenhum comentário ainda</p>';
          }
          
          document.getElementById('modal-comments-list').innerHTML = html;
      })
      .catch(error => {
          console.error('Erro ao carregar comentários:', error);
          document.getElementById('modal-comments-list').innerHTML = `
              <p class="text-center text-red-500 py-4">Erro ao carregar comentários</p>
          `;
      });
}

function toggleComentarios(postId) {
  abrirModalComentarios(postId);
}

document.addEventListener('click', function(e) {
  const modal = document.getElementById('modal-comentarios');
  if (e.target === modal) {
      fecharModalComentarios();
  }
});

function formatarDataModal(data) {
  const agora = new Date();
  const dataComentario = new Date(data);
  const diff = Math.floor((agora - dataComentario) / 1000); // diferença em segundos
  
  if (diff < 60) return 'agora mesmo';
  if (diff < 3600) return `${Math.floor(diff/60)} min atrás`;
  if (diff < 86400) return `${Math.floor(diff/3600)} h atrás`;
  
  // Formatar data completa se for mais de 1 dia
  return dataComentario.toLocaleDateString('pt-BR', {
      day: '2-digit',
      month: '2-digit',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
  });
}

document.getElementById('video-input').addEventListener('change', function(e) {
  const file = this.files[0];
  if (!file) return;
  
  // Mostrar preview do vídeo
  const videoPreview = document.getElementById('video-preview');
  const videoSource = document.getElementById('video-source');
  const progressBar = document.createElement('div');
  
  videoSource.src = URL.createObjectURL(file);
  videoPreview.classList.remove('hidden');
  videoPlayer.load();
  
  // Criar barra de progresso
  progressBar.className = 'w-full bg-gray-200 rounded-full h-2.5 mt-2';
  progressBar.innerHTML = `
      <div id="upload-progress" class="bg-red-600 h-2.5 rounded-full" style="width: 0%"></div>
      <div id="progress-text" class="text-xs text-center mt-1">0%</div>
  `;
  videoPreview.parentNode.insertBefore(progressBar, videoPreview.nextSibling);
  
  // Verificar tamanho e comprimir se necessário
  if (file.size > 100 * 1024 * 1024) { // >100MB
      if (confirm('Seu vídeo é muito grande. Recomendamos comprimir antes de enviar. Deseja comprimir?')) {
          compressVideo(file).then(compressedFile => {
              const dt = new DataTransfer();
              dt.items.add(compressedFile);
              this.files = dt.files;
              videoSource.src = URL.createObjectURL(compressedFile);
              videoPlayer.load();
          });
      }
  }
});
