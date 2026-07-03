async function load3DModel(filePath, canvasId, loadingElement, rootParam) {
    try {
        const modelColor = getColorFromURL();
        await loadScript('js/three.min.js');
        await loadScript('js/OrbitControls.js');
        const fileExt = filePath.split('.').pop().toLowerCase();
        if (fileExt === 'obj') {
            await loadScript('js/OBJLoader.js');
            await loadScript('js/MTLLoader.js');
        } else if (fileExt === 'stl') {
            await loadScript('js/STLLoader.js');
        } else {
            throw new Error(`Unsupported 3D format: ${fileExt}. Only OBJ and STL are supported.`);
        }
        const canvas = document.getElementById(canvasId);
        const scene = new THREE.Scene();
        scene.background = new THREE.Color(0x1a1a1a);
        const camera = new THREE.PerspectiveCamera(75, canvas.clientWidth / canvas.clientHeight, 0.1, 1000);
        const renderer = new THREE.WebGLRenderer({
            canvas: canvas,
            antialias: true,
            preserveDrawingBuffer: true
        });
        renderer.setSize(canvas.clientWidth, canvas.clientHeight);
        renderer.shadowMap.enabled = true;
        renderer.shadowMap.type = THREE.PCFSoftShadowMap;
        const controls = new THREE.OrbitControls(camera, renderer.domElement);
        controls.enableDamping = true;
        controls.dampingFactor = 0.05;
        const ambientLight = new THREE.AmbientLight(0x404040, 0.6);
        scene.add(ambientLight);
        const directionalLight = new THREE.DirectionalLight(0xffffff, 0.8);
        directionalLight.position.set(10, 10, 5);
        directionalLight.castShadow = true;
        scene.add(directionalLight);
        const fillLight = new THREE.DirectionalLight(modelColor, 0.3);
        fillLight.position.set(-5, 5, 5);
        scene.add(fillLight);
        const fileUrl = `api.php?action=raw&path=${encodeURIComponent(filePath)}&root=${rootParam || getActiveTabRoot()}`;
        let object;
        if (fileExt === 'obj') {
            object = await loadOBJModel(fileUrl, filePath, modelColor, rootParam || getActiveTabRoot());
        } else if (fileExt === 'stl') {
            object = await loadSTLModel(fileUrl, modelColor);
        }
        if (object) {
            scene.add(object);
            setupCameraForModel(object, camera, controls);
            loadingElement.style.display = 'none';
            function animate() {
                requestAnimationFrame(animate);
                controls.update();
                renderer.render(scene, camera);
            }
            animate();
            const resizeObserver = new ResizeObserver(() => {
                camera.aspect = canvas.clientWidth / canvas.clientHeight;
                camera.updateProjectionMatrix();
                renderer.setSize(canvas.clientWidth, canvas.clientHeight);
            });
            resizeObserver.observe(canvas);
        } else {
            throw new Error('Failed to load model');
        }
    } catch (error) {
        console.error('Error loading 3D model:', error);
        loadingElement.textContent = t(i18n, 'errorLoading3DModel') + ': ' + error.message;
        loadingElement.style.color = '#ff6b6b';
    }
}
function loadOBJModel(fileUrl, filePath, color, root = getActiveTabRoot()) {
    return new Promise((resolve, reject) => {
        const mtlFile = filePath.replace('.obj', '.mtl');
        const mtlUrl = `api.php?action=raw&path=${encodeURIComponent(mtlFile)}&root=${root}`;
        fetch(mtlUrl)
            .then(response => {
                if (response.ok) {
                    const mtlLoader = new THREE.MTLLoader();
                    mtlLoader.setCrossOrigin(true);
                    return mtlLoader.loadAsync(mtlUrl);
                } else {
                    return null;
                }
            })
            .then(materials => {
                const objLoader = new THREE.OBJLoader();
                if (materials) {
                    materials.preload();
                    objLoader.setMaterials(materials);
                }
                objLoader.load(fileUrl, (object) => {
                    applyColorToObject(object, color);
                    resolve(object);
                }, undefined, reject);
            })
            .catch(() => {
                const objLoader = new THREE.OBJLoader();
                objLoader.load(fileUrl, (object) => {
                    applyColorToObject(object, color);
                    resolve(object);
                }, undefined, reject);
            });
    });
}
function applyColorToObject(object, color) {
    object.traverse((child) => {
        if (child.isMesh) {
            if (child.material) {
                if (Array.isArray(child.material)) {
                    child.material.forEach(mat => {
                        if (mat.color) {
                            mat.color.set(color);
                        }
                    });
                } else {
                    if (child.material.color) {
                        child.material.color.set(color);
                    }
                }
            }
        }
    });
}
function loadSTLModel(fileUrl, color) {
    return new Promise((resolve, reject) => {
        const stlLoader = new THREE.STLLoader();
        stlLoader.load(fileUrl, (geometry) => {
            const material = new THREE.MeshStandardMaterial({
                color: color,
                roughness: 0.7,
                metalness: 0.2,
                flatShading: true
            });
            const mesh = new THREE.Mesh(geometry, material);
            resolve(mesh);
        }, undefined, reject);
    });
}
function getColorFromURL() {
    const urlParams = new URLSearchParams(window.location.search);
    const colorParam = urlParams.get('color');
    if (colorParam) {
        return colorParam.startsWith('#') ? colorParam : '#' + colorParam;
    }
    return '#989898ff';
}
function setupCameraForModel(object, camera, controls) {
    const box = new THREE.Box3().setFromObject(object);
    const center = box.getCenter(new THREE.Vector3());
    const size = box.getSize(new THREE.Vector3());
    const maxDim = Math.max(size.x, size.y, size.z);
    const fov = camera.fov * (Math.PI / 180);
    let cameraZ = Math.abs(maxDim / Math.sin(fov / 2));
    cameraZ *= 1.5;
    camera.position.set(center.x, center.y, center.z + cameraZ);
    controls.target = center;
    controls.update();
}
function loadScript(src) {
    return new Promise((resolve, reject) => {
        if (document.querySelector(`script[src="${src}"]`)) {
            resolve();
            return;
        }
        const shouldBypassAmd = /(^|\/)(three\.min|OrbitControls|OBJLoader|MTLLoader|STLLoader|FBXLoader|3MFLoader|dxf-parser\.min|libredwg-web\.umd\.min)\.js(?:$|\?)/.test(src);
        const previousDefine = window.define;
        const hadOwnDefine = Object.prototype.hasOwnProperty.call(window, 'define');
        if (shouldBypassAmd) {
            try {
                window.define = undefined;
            } catch (err) {
                console.warn('Failed to temporarily disable AMD define for', src, err);
            }
        }
        const restoreDefine = () => {
            if (!shouldBypassAmd) return;
            if (hadOwnDefine) {
                window.define = previousDefine;
            } else {
                delete window.define;
            }
        };
        const script = document.createElement('script');
        script.src = src;
        script.onload = () => {
            restoreDefine();
            resolve();
        };
        script.onerror = () => {
            restoreDefine();
            reject(new Error(`Failed to load script: ${src}`));
        };
        document.head.appendChild(script);
    });
}
