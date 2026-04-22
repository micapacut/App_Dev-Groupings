/**
 * 3D Viewer: load .glb, .gltf, .obj via file input; show status indicators.
 * Uses Three.js (GLTFLoader, OBJLoader, OrbitControls).
 */

(function () {
  const fileInput = document.getElementById('file-3d');
  const canvasWrap = document.getElementById('viewer-canvas-wrap');
  const canvas = document.getElementById('viewer-canvas');
  const placeholder = document.getElementById('viewer-placeholder');
  const loadingEl = document.getElementById('viewer-loading');
  const statusEl = document.getElementById('viewer-status');
  const resetBtn = document.getElementById('viewer-reset');

  let scene, camera, renderer, controls, currentModel = null;

  function setStatus(message, type) {
    if (!statusEl) return;
    statusEl.textContent = message;
    statusEl.className = 'viewer-status ' + (type || '');
    statusEl.classList.remove('hidden');
  }

  function clearStatus() {
    if (statusEl) {
      statusEl.classList.add('hidden');
      statusEl.textContent = '';
    }
  }

  function initThree() {
    if (renderer) {
      if (canvasWrap && canvas) {
        const w = Math.max(canvasWrap.clientWidth || 800, 300);
        const h = 420;
        camera.aspect = w / h;
        camera.updateProjectionMatrix();
        renderer.setSize(w, h);
      }
      return;
    }
    const width = Math.max(canvasWrap ? canvasWrap.clientWidth : 800, 300);
    const height = 420;

    scene = new THREE.Scene();
    scene.background = new THREE.Color(0x1a2332);
    // Low-intensity lighting so model colors/textures are not blown out to white
    scene.add(new THREE.AmbientLight(0xffffff, 0.15));
    scene.add(new THREE.HemisphereLight(0xffffff, 0x444466, 0.2));
    const dir1 = new THREE.DirectionalLight(0xffffff, 0.4);
    dir1.position.set(5, 10, 7);
    scene.add(dir1);
    const dir2 = new THREE.DirectionalLight(0xffffff, 0.2);
    dir2.position.set(-5, 5, -5);
    scene.add(dir2);

    camera = new THREE.PerspectiveCamera(50, width / height, 0.1, 1000);
    camera.position.set(2, 2, 2);

    renderer = new THREE.WebGLRenderer({ canvas: canvas, antialias: true });
    renderer.setSize(width, height);
    renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
    if (renderer.outputEncoding !== undefined) renderer.outputEncoding = THREE.sRGBEncoding;
    if (renderer.toneMapping !== undefined) {
      renderer.toneMapping = THREE.ACESFilmicToneMapping;
      renderer.toneMappingExposure = 0.7;
    }

    controls = new THREE.OrbitControls(camera, canvas);
    controls.target.set(0, 0, 0);
    controls.enableDamping = true;
    controls.dampingFactor = 0.05;
    controls.screenSpacePanning = true;
    controls.minDistance = 0.2;
    controls.maxDistance = 50;

    function animate() {
      requestAnimationFrame(animate);
      controls.update();
      renderer.render(scene, camera);
    }
    animate();

    window.addEventListener('resize', function () {
      if (!camera || !renderer || !canvasWrap) return;
      const w = Math.max(canvasWrap.clientWidth || 800, 300);
      camera.aspect = w / height;
      camera.updateProjectionMatrix();
      renderer.setSize(w, height);
    });
  }

  function showPlaceholder(show) {
    if (placeholder) placeholder.classList.toggle('hidden', !show);
  }

  function showLoading(show) {
    if (loadingEl) loadingEl.classList.toggle('hidden', !show);
  }

  function disposeObject(obj) {
    if (!obj) return;
    if (obj.geometry) obj.geometry.dispose();
    if (obj.material) {
      if (Array.isArray(obj.material)) obj.material.forEach(function (m) { m.dispose(); });
      else obj.material.dispose();
    }
    if (obj.children && obj.children.length) {
      obj.children.forEach(disposeObject);
    }
  }

  function clearModel() {
    if (currentModel) {
      scene.remove(currentModel);
      disposeObject(currentModel);
      currentModel = null;
    }
  }

  function ensureMaterial(obj) {
    if (obj.material) return;
    obj.material = new THREE.MeshPhongMaterial({
      color: 0x888888,
      shininess: 30,
      specular: 0x222222
    });
  }

  /**
   * Fix materials so model colors/textures display correctly (not all white).
   * - Set texture encoding to sRGB for correct color
   * - Enable vertex colors if geometry has them
   * - Tone down PBR so lighting does not blow out; fix unlit materials
   */
  function fixMaterialsForDisplay(object) {
    object.traverse(function (child) {
      if (!(child instanceof THREE.Mesh)) return;
      var mesh = child;
      var mat = mesh.material;
      if (!mat) return;
      var mats = Array.isArray(mat) ? mat : [mat];
      mats.forEach(function (m) {
        if (m.map && m.map.encoding !== undefined) m.map.encoding = THREE.sRGBEncoding;
        if (m.emissiveMap && m.emissiveMap.encoding !== undefined) m.emissiveMap.encoding = THREE.sRGBEncoding;
        if (mesh.geometry && mesh.geometry.attributes && mesh.geometry.attributes.color) {
          m.vertexColors = true;
        }
        if (m.metalness !== undefined) {
          m.envMapIntensity = (m.envMapIntensity !== undefined) ? Math.min(m.envMapIntensity, 0.5) : 0.3;
        }
        if (m.type === 'MeshBasicMaterial' && !m.map && m.color.getHex && m.color.getHex() === 0xffffff) {
          m.color.setHex(0xe0e0e0);
        }
      });
    });
  }

  /**
   * Scale model to fit in view, then center at origin so zoom orbits around center.
   * Order: scale first, then center (so zoom doesn't make the model drift to the side).
   */
  function centerAndScale(object) {
    var box = new THREE.Box3().setFromObject(object);
    var size = box.getSize(new THREE.Vector3());
    var maxDim = Math.max(size.x, size.y, size.z);
    if (maxDim > 0) {
      var scale = 2 / maxDim;
      object.scale.multiplyScalar(scale);
    }
    box.setFromObject(object);
    var center = box.getCenter(new THREE.Vector3());
    object.position.sub(center);
  }

  function loadFromFile(file) {
    if (typeof THREE === 'undefined' || typeof THREE.OBJLoader === 'undefined') {
      setStatus('3D library failed to load. Check console (F12).', 'error');
      return;
    }
    var ext = (file.name.split('.').pop() || '').toLowerCase();
    if (['glb', 'gltf', 'obj'].indexOf(ext) === -1) {
      setStatus('Unsupported format. Use .glb, .gltf, or .obj.', 'error');
      return;
    }

    clearStatus();
    setStatus('File uploaded successfully.', 'success');
    showLoading(true);
    showPlaceholder(false);
    clearModel();
    initThree();

    var url = URL.createObjectURL(file);
    var loader = ext === 'obj' ? new THREE.OBJLoader() : new THREE.GLTFLoader();

    function onLoad(modelOrResult) {
      URL.revokeObjectURL(url);
      var model = modelOrResult.scene || modelOrResult;
      if (!model) {
        showLoading(false);
        setStatus('Loaded file has no model.', 'error');
        return;
      }
      if (ext === 'obj') {
        model.traverse(function (child) {
          if ((child instanceof THREE.Mesh) || child.isMesh) ensureMaterial(child);
        });
      }
      fixMaterialsForDisplay(model);
      scene.add(model);
      centerAndScale(model);
      currentModel = model;
      if (controls) controls.target.set(0, 0, 0);
      showLoading(false);
      setStatus('Viewed successfully.', 'success');
    }

    function onError(err) {
      URL.revokeObjectURL(url);
      console.error(err);
      showLoading(false);
      showPlaceholder(true);
      setStatus('Failed to load model: ' + (err && err.message ? err.message : 'unknown error'), 'error');
    }

    if (ext === 'obj') {
      loader.load(url, onLoad, undefined, onError);
    } else {
      loader.load(url, function (gltf) { onLoad(gltf); }, undefined, onError);
    }
  }

  fileInput.addEventListener('change', function () {
    var file = this.files[0];
    if (file) loadFromFile(file);
    this.value = '';
  });

  resetBtn.addEventListener('click', function () {
    if (!camera || !controls) return;
    camera.position.set(2, 2, 2);
    controls.target.set(0, 0, 0);
    controls.update();
  });

  canvasWrap.addEventListener('dragover', function (e) {
    e.preventDefault();
    e.stopPropagation();
  });
  canvasWrap.addEventListener('drop', function (e) {
    e.preventDefault();
    e.stopPropagation();
    var file = e.dataTransfer.files[0];
    if (file) loadFromFile(file);
  });
})();
