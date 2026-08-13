<script setup lang="ts">
// Dependency-free STL viewer (raw WebGL). The npm registry is unreachable in this
// environment, so three.js isn't an option; this parses binary + ASCII STL and
// renders it with a small Phong/headlamp shader. Orbit by dragging, zoom by wheel.
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps<{ src: string }>();

const canvas = ref<HTMLCanvasElement | null>(null);
const error = ref('');
const loading = ref(true);

let gl: WebGLRenderingContext | null = null;
let program: WebGLProgram | null = null;
let buffer: WebGLBuffer | null = null;
let triCount = 0;
let raf = 0;
let disposed = false;

// View state
let rotX = -1.2;
let rotY = 0.6;
let dist = 3;
let dragging = false;
let lastX = 0;
let lastY = 0;

function parseStl(buf: ArrayBuffer): Float32Array {
  const dv = new DataView(buf);
  // Binary if the reported triangle count matches the byte length exactly.
  let isBinary = false;
  if (buf.byteLength >= 84) {
    const n = dv.getUint32(80, true);
    if (84 + n * 50 === buf.byteLength) isBinary = true;
  }
  if (isBinary) return parseBinary(dv);
  return parseAscii(new TextDecoder().decode(buf));
}

function parseBinary(dv: DataView): Float32Array {
  const n = dv.getUint32(80, true);
  const out = new Float32Array(n * 18); // 3 verts * (pos3 + normal3)
  let o = 0;
  let p = 84;
  for (let i = 0; i < n; i++) {
    const nx = dv.getFloat32(p, true), ny = dv.getFloat32(p + 4, true), nz = dv.getFloat32(p + 8, true);
    p += 12;
    for (let v = 0; v < 3; v++) {
      out[o++] = dv.getFloat32(p, true);
      out[o++] = dv.getFloat32(p + 4, true);
      out[o++] = dv.getFloat32(p + 8, true);
      out[o++] = nx; out[o++] = ny; out[o++] = nz;
      p += 12;
    }
    p += 2; // attribute byte count
  }
  return out;
}

function parseAscii(text: string): Float32Array {
  const verts: number[] = [];
  const nums = /facet\s+normal\s+([^\n]+)|vertex\s+([^\n]+)/g;
  let normal = [0, 0, 0];
  let tri: number[] = [];
  let m: RegExpExecArray | null;
  while ((m = nums.exec(text)) !== null) {
    if (m[1]) {
      normal = m[1].trim().split(/\s+/).map(Number);
    } else if (m[2]) {
      const v = m[2].trim().split(/\s+/).map(Number);
      tri.push(v[0], v[1], v[2], normal[0] || 0, normal[1] || 0, normal[2] || 0);
      if (tri.length === 18) { verts.push(...tri); tri = []; }
    }
  }
  return new Float32Array(verts);
}

// Fit the model into a unit-ish box: center + uniform scale baked into the data.
function normalize(data: Float32Array): Float32Array {
  let minX = Infinity, minY = Infinity, minZ = Infinity, maxX = -Infinity, maxY = -Infinity, maxZ = -Infinity;
  for (let i = 0; i < data.length; i += 6) {
    minX = Math.min(minX, data[i]); maxX = Math.max(maxX, data[i]);
    minY = Math.min(minY, data[i + 1]); maxY = Math.max(maxY, data[i + 1]);
    minZ = Math.min(minZ, data[i + 2]); maxZ = Math.max(maxZ, data[i + 2]);
  }
  const cx = (minX + maxX) / 2, cy = (minY + maxY) / 2, cz = (minZ + maxZ) / 2;
  const span = Math.max(maxX - minX, maxY - minY, maxZ - minZ) || 1;
  const s = 2 / span;
  for (let i = 0; i < data.length; i += 6) {
    data[i] = (data[i] - cx) * s;
    data[i + 1] = (data[i + 1] - cy) * s;
    data[i + 2] = (data[i + 2] - cz) * s;
  }
  return data;
}

const VS = `attribute vec3 aPos; attribute vec3 aNormal;
uniform mat4 uMVP; uniform mat4 uModel;
varying vec3 vNormal; varying vec3 vPos;
void main(){ vNormal = mat3(uModel)*aNormal; vPos=(uModel*vec4(aPos,1.0)).xyz; gl_Position = uMVP*vec4(aPos,1.0); }`;
const FS = `precision mediump float;
varying vec3 vNormal; varying vec3 vPos;
void main(){
  vec3 n = normalize(vNormal);
  // Headlamp + a little ambient so back faces aren't black.
  vec3 L = normalize(vec3(0.4,0.6,1.0));
  float d = max(dot(n,L), 0.0)*0.8 + 0.25;
  vec3 base = vec3(0.55,0.62,0.75);
  gl_FragColor = vec4(base*d, 1.0);
}`;

function compile(g: WebGLRenderingContext, type: number, src: string): WebGLShader {
  const sh = g.createShader(type)!;
  g.shaderSource(sh, src); g.compileShader(sh);
  if (!g.getShaderParameter(sh, g.COMPILE_STATUS)) throw new Error(g.getShaderInfoLog(sh) || 'shader');
  return sh;
}

// Minimal mat4 helpers (column-major).
function perspective(fovy: number, aspect: number, near: number, far: number): number[] {
  const f = 1 / Math.tan(fovy / 2), nf = 1 / (near - far);
  return [f / aspect, 0, 0, 0, 0, f, 0, 0, 0, 0, (far + near) * nf, -1, 0, 0, 2 * far * near * nf, 0];
}
function multiply(a: number[], b: number[]): number[] {
  const o = new Array(16).fill(0);
  for (let r = 0; r < 4; r++) for (let c = 0; c < 4; c++) for (let k = 0; k < 4; k++) o[c * 4 + r] += a[k * 4 + r] * b[c * 4 + k];
  return o;
}
function rotationXY(rx: number, ry: number, tz: number): number[] {
  const cx = Math.cos(rx), sx = Math.sin(rx), cy = Math.cos(ry), sy = Math.sin(ry);
  const rX = [1, 0, 0, 0, 0, cx, sx, 0, 0, -sx, cx, 0, 0, 0, 0, 1];
  const rY = [cy, 0, -sy, 0, 0, 1, 0, 0, sy, 0, cy, 0, 0, 0, 0, 1];
  const t = [1, 0, 0, 0, 0, 1, 0, 0, 0, 0, 1, 0, 0, 0, tz, 1];
  return multiply(t, multiply(rX, rY));
}

function render() {
  if (disposed || !gl || !program || !canvas.value) return;
  const g = gl, cv = canvas.value;
  const w = cv.clientWidth, h = cv.clientHeight;
  if (cv.width !== w || cv.height !== h) { cv.width = w; cv.height = h; }
  g.viewport(0, 0, cv.width, cv.height);
  g.clearColor(0, 0, 0, 0);
  g.enable(g.DEPTH_TEST);
  g.clear(g.COLOR_BUFFER_BIT | g.DEPTH_BUFFER_BIT);
  const model = rotationXY(rotX, rotY, 0);
  const view = [1, 0, 0, 0, 0, 1, 0, 0, 0, 0, 1, 0, 0, 0, -dist, 1];
  const proj = perspective(Math.PI / 4, (cv.width || 1) / (cv.height || 1), 0.1, 100);
  const mvp = multiply(proj, multiply(view, model));
  g.uniformMatrix4fv(g.getUniformLocation(program, 'uMVP'), false, mvp);
  g.uniformMatrix4fv(g.getUniformLocation(program, 'uModel'), false, model);
  g.drawArrays(g.TRIANGLES, 0, triCount * 3);
}
function schedule() { if (!raf) raf = requestAnimationFrame(() => { raf = 0; render(); }); }

async function load() {
  loading.value = true; error.value = '';
  try {
    const res = await fetch(props.src, { headers: { Accept: 'application/octet-stream' } });
    if (!res.ok) throw new Error(String(res.status));
    const data = normalize(parseStl(await res.arrayBuffer()));
    triCount = data.length / 18;
    if (!triCount) throw new Error('empty');
    const g = canvas.value!.getContext('webgl');
    if (!g) throw new Error('no-webgl');
    gl = g;
    program = g.createProgram()!;
    g.attachShader(program, compile(g, g.VERTEX_SHADER, VS));
    g.attachShader(program, compile(g, g.FRAGMENT_SHADER, FS));
    g.linkProgram(program);
    g.useProgram(program);
    buffer = g.createBuffer();
    g.bindBuffer(g.ARRAY_BUFFER, buffer);
    g.bufferData(g.ARRAY_BUFFER, data, g.STATIC_DRAW);
    const pos = g.getAttribLocation(program, 'aPos');
    const nrm = g.getAttribLocation(program, 'aNormal');
    g.enableVertexAttribArray(pos);
    g.vertexAttribPointer(pos, 3, g.FLOAT, false, 24, 0);
    g.enableVertexAttribArray(nrm);
    g.vertexAttribPointer(nrm, 3, g.FLOAT, false, 24, 12);
    loading.value = false;
    render();
  } catch (e) {
    error.value = String((e as Error).message || e);
    loading.value = false;
  }
}

function onDown(e: PointerEvent) { dragging = true; lastX = e.clientX; lastY = e.clientY; (e.target as Element).setPointerCapture?.(e.pointerId); }
function onMove(e: PointerEvent) {
  if (!dragging) return;
  rotY += (e.clientX - lastX) * 0.01; rotX += (e.clientY - lastY) * 0.01;
  lastX = e.clientX; lastY = e.clientY; schedule();
}
function onUp() { dragging = false; }
function onWheel(e: WheelEvent) { e.preventDefault(); dist = Math.min(20, Math.max(1.2, dist + (e.deltaY > 0 ? 0.3 : -0.3))); schedule(); }

onMounted(load);
watch(() => props.src, load);
onBeforeUnmount(() => {
  disposed = true;
  if (raf) cancelAnimationFrame(raf);
  if (gl && buffer) gl.deleteBuffer(buffer);
});
</script>

<template>
  <div class="relative flex items-center justify-center">
    <canvas
      ref="canvas"
      class="h-full w-full touch-none"
      @pointerdown="onDown" @pointermove="onMove" @pointerup="onUp" @pointerleave="onUp" @wheel="onWheel"
    ></canvas>
    <div v-if="loading" class="absolute text-sm text-[var(--ll-muted)]">…</div>
    <div v-else-if="error" class="absolute text-sm text-[var(--ll-muted)]">STL: {{ error }}</div>
  </div>
</template>
