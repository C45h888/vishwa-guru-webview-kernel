// vite.config.ts
import { defineConfig } from "file:///app/node_modules/vite/dist/node/index.js";
import laravel from "file:///app/node_modules/laravel-vite-plugin/dist/index.js";
import { svelte } from "file:///app/node_modules/@sveltejs/vite-plugin-svelte/src/index.js";
import { fileURLToPath } from "node:url";
import { dirname, resolve } from "node:path";
var __vite_injected_original_import_meta_url = "file:///app/vite.config.ts";
var __dirname = dirname(fileURLToPath(__vite_injected_original_import_meta_url));
var vite_config_default = defineConfig({
  plugins: [
    laravel({
      input: ["resources/css/app.css", "resources/js/app.ts"],
      refresh: true
    }),
    svelte()
  ],
  resolve: {
    alias: {
      $shared: resolve(__dirname, "resources/js/shared"),
      $domains: resolve(__dirname, "resources/js/domains")
    }
  },
  build: {
    outDir: "public/build",
    // Vite 5 defaults manifest to `.vite/manifest.json` but Laravel 10's
    // Vite helper (Illuminate\Foundation\Vite::manifestPath) still expects
    // `public/build/manifest.json`. Pass the filename explicitly so the
    // framework can resolve assets and emit <link>/<script> tags.
    manifest: "manifest.json"
  }
});
export {
  vite_config_default as default
};
//# sourceMappingURL=data:application/json;base64,ewogICJ2ZXJzaW9uIjogMywKICAic291cmNlcyI6IFsidml0ZS5jb25maWcudHMiXSwKICAic291cmNlc0NvbnRlbnQiOiBbImNvbnN0IF9fdml0ZV9pbmplY3RlZF9vcmlnaW5hbF9kaXJuYW1lID0gXCIvYXBwXCI7Y29uc3QgX192aXRlX2luamVjdGVkX29yaWdpbmFsX2ZpbGVuYW1lID0gXCIvYXBwL3ZpdGUuY29uZmlnLnRzXCI7Y29uc3QgX192aXRlX2luamVjdGVkX29yaWdpbmFsX2ltcG9ydF9tZXRhX3VybCA9IFwiZmlsZTovLy9hcHAvdml0ZS5jb25maWcudHNcIjtpbXBvcnQgeyBkZWZpbmVDb25maWcgfSBmcm9tICd2aXRlJztcbmltcG9ydCBsYXJhdmVsIGZyb20gJ2xhcmF2ZWwtdml0ZS1wbHVnaW4nO1xuaW1wb3J0IHsgc3ZlbHRlIH0gZnJvbSAnQHN2ZWx0ZWpzL3ZpdGUtcGx1Z2luLXN2ZWx0ZSc7XG5pbXBvcnQgeyBmaWxlVVJMVG9QYXRoIH0gZnJvbSAnbm9kZTp1cmwnO1xuaW1wb3J0IHsgZGlybmFtZSwgcmVzb2x2ZSB9IGZyb20gJ25vZGU6cGF0aCc7XG5cbmNvbnN0IF9fZGlybmFtZSA9IGRpcm5hbWUoZmlsZVVSTFRvUGF0aChpbXBvcnQubWV0YS51cmwpKTtcblxuZXhwb3J0IGRlZmF1bHQgZGVmaW5lQ29uZmlnKHtcbiAgICBwbHVnaW5zOiBbXG4gICAgICAgIGxhcmF2ZWwoe1xuICAgICAgICAgICAgaW5wdXQ6IFsncmVzb3VyY2VzL2Nzcy9hcHAuY3NzJywgJ3Jlc291cmNlcy9qcy9hcHAudHMnXSxcbiAgICAgICAgICAgIHJlZnJlc2g6IHRydWUsXG4gICAgICAgIH0pLFxuICAgICAgICBzdmVsdGUoKSxcbiAgICBdLFxuICAgIHJlc29sdmU6IHtcbiAgICAgICAgYWxpYXM6IHtcbiAgICAgICAgICAgICRzaGFyZWQ6IHJlc29sdmUoX19kaXJuYW1lLCAncmVzb3VyY2VzL2pzL3NoYXJlZCcpLFxuICAgICAgICAgICAgJGRvbWFpbnM6IHJlc29sdmUoX19kaXJuYW1lLCAncmVzb3VyY2VzL2pzL2RvbWFpbnMnKSxcbiAgICAgICAgfSxcbiAgICB9LFxuICAgIGJ1aWxkOiB7XG4gICAgICAgIG91dERpcjogJ3B1YmxpYy9idWlsZCcsXG4gICAgICAgIC8vIFZpdGUgNSBkZWZhdWx0cyBtYW5pZmVzdCB0byBgLnZpdGUvbWFuaWZlc3QuanNvbmAgYnV0IExhcmF2ZWwgMTAnc1xuICAgICAgICAvLyBWaXRlIGhlbHBlciAoSWxsdW1pbmF0ZVxcRm91bmRhdGlvblxcVml0ZTo6bWFuaWZlc3RQYXRoKSBzdGlsbCBleHBlY3RzXG4gICAgICAgIC8vIGBwdWJsaWMvYnVpbGQvbWFuaWZlc3QuanNvbmAuIFBhc3MgdGhlIGZpbGVuYW1lIGV4cGxpY2l0bHkgc28gdGhlXG4gICAgICAgIC8vIGZyYW1ld29yayBjYW4gcmVzb2x2ZSBhc3NldHMgYW5kIGVtaXQgPGxpbms+LzxzY3JpcHQ+IHRhZ3MuXG4gICAgICAgIG1hbmlmZXN0OiAnbWFuaWZlc3QuanNvbicsXG4gICAgfSxcbn0pO1xuIl0sCiAgIm1hcHBpbmdzIjogIjtBQUE4TCxTQUFTLG9CQUFvQjtBQUMzTixPQUFPLGFBQWE7QUFDcEIsU0FBUyxjQUFjO0FBQ3ZCLFNBQVMscUJBQXFCO0FBQzlCLFNBQVMsU0FBUyxlQUFlO0FBSitFLElBQU0sMkNBQTJDO0FBTWpLLElBQU0sWUFBWSxRQUFRLGNBQWMsd0NBQWUsQ0FBQztBQUV4RCxJQUFPLHNCQUFRLGFBQWE7QUFBQSxFQUN4QixTQUFTO0FBQUEsSUFDTCxRQUFRO0FBQUEsTUFDSixPQUFPLENBQUMseUJBQXlCLHFCQUFxQjtBQUFBLE1BQ3RELFNBQVM7QUFBQSxJQUNiLENBQUM7QUFBQSxJQUNELE9BQU87QUFBQSxFQUNYO0FBQUEsRUFDQSxTQUFTO0FBQUEsSUFDTCxPQUFPO0FBQUEsTUFDSCxTQUFTLFFBQVEsV0FBVyxxQkFBcUI7QUFBQSxNQUNqRCxVQUFVLFFBQVEsV0FBVyxzQkFBc0I7QUFBQSxJQUN2RDtBQUFBLEVBQ0o7QUFBQSxFQUNBLE9BQU87QUFBQSxJQUNILFFBQVE7QUFBQTtBQUFBO0FBQUE7QUFBQTtBQUFBLElBS1IsVUFBVTtBQUFBLEVBQ2Q7QUFDSixDQUFDOyIsCiAgIm5hbWVzIjogW10KfQo=
