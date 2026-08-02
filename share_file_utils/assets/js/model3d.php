<?php if ($fileType === '3dmodel'): ?>
<script type="module">


import * as THREE from 'three';
import { OrbitControls } from 'three/addons/controls/OrbitControls.js';
import { STLLoader } from 'three/addons/loaders/STLLoader.js';
import { ThreeMFLoader } from 'three/addons/loaders/3MFLoader.js';

class ModelViewer {
    constructor() {
        this.scene = new THREE.Scene();
        this.scene.background = null;
        
        this.camera = new THREE.PerspectiveCamera(45, window.innerWidth / window.innerHeight, 0.01, 1000);
        this.camera.position.set(5, 5, 10);
        
        this.renderer = new THREE.WebGLRenderer({ 
            canvas: document.getElementById('model-canvas'), 
            antialias: true,
            alpha: true,
            powerPreference: "high-performance",
            precision: "highp"
        });
        
        this.renderer.setSize(window.innerWidth, window.innerHeight);
        this.renderer.setPixelRatio(window.devicePixelRatio);
        this.renderer.shadowMap.enabled = true;
        this.renderer.shadowMap.type = THREE.PCFSoftShadowMap;
        this.renderer.outputColorSpace = THREE.SRGBColorSpace;
        this.renderer.toneMapping = THREE.ACESFilmicToneMapping;
        this.renderer.toneMappingExposure = 1.2;
        
        this.controls = new OrbitControls(this.camera, this.renderer.domElement);
        this.controls.enableDamping = true;
        this.controls.dampingFactor = 0.05;
        this.controls.autoRotate = true;
        this.controls.autoRotateSpeed = 2.0;
        this.controls.enableZoom = true;
        this.controls.rotateSpeed = 1.0;
        this.controls.zoomSpeed = 1.2;
        this.controls.panSpeed = 2.0;
        this.controls.minDistance = 0.01;
        this.controls.maxDistance = 1000;
        this.controls.target.set(0, 0, 0);
        
        // Lighting
        this.setupLighting();
        
        // Load model
        this.loadModel();
        
        // Start animation
        this.animate();
    }
    
    setupLighting() {
        const ambientLight = new THREE.AmbientLight(0xffffff, 0.6);
        this.scene.add(ambientLight);
        
        const mainLight = new THREE.DirectionalLight(0xffffff, 1.0);
        mainLight.position.set(10, 15, 10);
        mainLight.castShadow = true;
        mainLight.shadow.mapSize.width = 2048;
        mainLight.shadow.mapSize.height = 2048;
        mainLight.shadow.camera.near = 0.5;
        mainLight.shadow.camera.far = 50;
        mainLight.shadow.camera.left = -20;
        mainLight.shadow.camera.right = 20;
        mainLight.shadow.camera.top = 20;
        mainLight.shadow.camera.bottom = -20;
        this.scene.add(mainLight);
        
        const fillLight = new THREE.DirectionalLight(0xffffff, 0.4);
        fillLight.position.set(-10, 5, -5);
        this.scene.add(fillLight);
        
        const backLight = new THREE.DirectionalLight(0xffffff, 0.3);
        backLight.position.set(0, 5, -10);
        this.scene.add(backLight);
        
        // Grid helper
        const gridHelper = new THREE.GridHelper(20, 20, 0x888888, 0x444444);
        gridHelper.position.y = 0;
        this.scene.add(gridHelper);
    }
    
    loadModel() {
        const url = <?php echo json_encode($modelDisplayUrl); ?>;
        const extension = '<?php echo strtolower(pathinfo($fileName, PATHINFO_EXTENSION)); ?>';
        let loader;
        
        if (extension === 'stl') {
            loader = new STLLoader();
        } else if (extension === '3mf') {
            loader = new ThreeMFLoader();
        } else {
            console.error('Unsupported 3D format');
            return;
        }
        
        const loadingDiv = document.createElement('div');
        loadingDiv.className = 'onlyoffice-loading';
        loadingDiv.innerHTML = '<div class="spinner"></div><p>Loading 3D model...</p>';
        document.querySelector('.model-viewer-container').appendChild(loadingDiv);
        
        loader.load(
            url,
            (geometry) => {
                loadingDiv.remove();
                
                // Create material with theme color
                const material = new THREE.MeshPhysicalMaterial({ 
                    color: 0x<?php echo $color; ?>,
                    roughness: 0.2,
                    metalness: 0.0,
                    clearcoat: 0.5,
                    clearcoatRoughness: 0.1,
                    emissive: 0x000000,
                    flatShading: false,
                    side: THREE.DoubleSide
                });
                
                const mesh = new THREE.Mesh(geometry, material);
                mesh.castShadow = true;
                mesh.receiveShadow = true;
                
                // Centering and scaling
                if (!geometry.boundingBox) {
                    geometry.computeBoundingBox();
                }
                
                const box = geometry.boundingBox;
                const size = new THREE.Vector3();
                box.getSize(size);
                const center = new THREE.Vector3();
                box.getCenter(center);
                
                const maxDim = Math.max(size.x, size.y, size.z);
                const scale = maxDim > 0 ? 8 / maxDim : 1;
                
                mesh.position.set(-center.x * scale, -center.y * scale, -center.z * scale);
                mesh.scale.setScalar(scale);
                
                this.scene.add(mesh);
                
                // Model info
                document.getElementById('model-info').innerHTML = 
                    `3D Model • ${extension.toUpperCase()} • ${size.x.toFixed(2)}x${size.y.toFixed(2)}x${size.z.toFixed(2)}`;
                
                // Auto-zoom
                this.fitCameraToObject(mesh);
            },
            (xhr) => {
                console.log((xhr.loaded / xhr.total * 100) + '% loaded');
            },
            (error) => {
                loadingDiv.remove();
                console.error('Error loading model:', error);
                const errorDiv = document.createElement('div');
                errorDiv.className = 'error-view';
                errorDiv.innerHTML = '<svg class="error-icon" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg><div>Error loading 3D model</div>';
                document.querySelector('.model-viewer-container').appendChild(errorDiv);
            }
        );
    }
    
    fitCameraToObject(object) {
        const box = new THREE.Box3().setFromObject(object);
        const center = box.getCenter(new THREE.Vector3());
        const size = box.getSize(new THREE.Vector3());
        
        this.controls.target.copy(center);
        
        const maxDim = Math.max(size.x, size.y, size.z);
        const fov = this.camera.fov * (Math.PI / 180);
        let cameraDistance = maxDim / (2 * Math.tan(fov / 2));
        cameraDistance *= 1.2;
        
        this.camera.position.set(
            center.x + cameraDistance,
            center.y + cameraDistance * 0.5,
            center.z + cameraDistance
        );
        
        this.camera.lookAt(center);
        this.controls.update();
    }
    
    animate() {
        requestAnimationFrame(() => this.animate());
        if (this.controls) {
            this.controls.update();
        }
        this.renderer.render(this.scene, this.camera);
    }
    
    rotateLeft() {
        this.controls.autoRotate = false;
        this.scene.rotation.y += 0.1;
    }
    
    rotateRight() {
        this.controls.autoRotate = false;
        this.scene.rotation.y -= 0.1;
    }
    
    resetView() {
        this.controls.autoRotate = false;
        const models = this.scene.children.filter(child => 
            child.isMesh && child.material && !child.material.transparent
        );
        if (models.length > 0) {
            this.fitCameraToObject(models[0]);
        }
    }
    
    toggleAutoRotate() {
        this.controls.autoRotate = !this.controls.autoRotate;
    }
}

const modelViewer = new ModelViewer();
window.modelControls = {
    rotateLeft: () => modelViewer.rotateLeft(),
    rotateRight: () => modelViewer.rotateRight(),
    resetView: () => modelViewer.resetView(),
    toggleAutoRotate: () => modelViewer.toggleAutoRotate()
};
window.addEventListener('resize', () => {
    modelViewer.camera.aspect = window.innerWidth / window.innerHeight;
    modelViewer.camera.updateProjectionMatrix();
    modelViewer.renderer.setSize(window.innerWidth, window.innerHeight);
});
</script>
<?php endif; ?>
