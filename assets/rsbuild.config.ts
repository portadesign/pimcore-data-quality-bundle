import { defineConfig } from '@rsbuild/core'
import { pluginReact } from '@rsbuild/plugin-react'
import { pluginModuleFederation } from '@module-federation/rsbuild-plugin'
import { pluginGenerateEntrypoints } from '@pimcore/studio-ui-bundle/rsbuild/plugins'
import { createDynamicRemote } from '@pimcore/studio-ui-bundle/rsbuild/utils'
import path from 'path'
import fs from 'fs'
import { v4 } from 'uuid'
import packages from './package.json'

const buildId = v4()
const buildPath = path.resolve(__dirname, '..', 'public', 'build', buildId)

if (fs.existsSync(path.resolve(__dirname, '..', 'public', 'build'))) {
  fs.readdirSync(path.resolve(__dirname, '..', 'public', 'build')).forEach((file) => {
    fs.rmSync(path.resolve(__dirname, '..', 'public', 'build', file), { recursive: true })
  })
}

if (!fs.existsSync(buildPath)) {
  fs.mkdirSync(buildPath, { recursive: true })
}

const nodeEnv = process.env.NODE_ENV
let env: 'development' | 'production' = 'production'

const isDevServer = nodeEnv === 'dev-server'
if (nodeEnv !== env) {
  env = 'development'
}

export default defineConfig({
  mode: env,
  server: {
    port: 3034
  },
  dev: {
    ...(!isDevServer ? { assetPrefix: '/bundles/portadesigndataquality/build/' + buildId } : {}),
    client: {
      host: 'localhost',
      port: 3034,
      protocol: 'ws'
    }
  },
  source: {
    entry: {
      main: './js/src/main.ts'
    },
    decorators: {
      version: 'legacy'
    }
  },
  output: {
    manifest: true,
    assetPrefix: '/bundles/portadesigndataquality/build/' + buildId,
    distPath: {
      root: buildPath
    }
  },
  tools: {
    bundlerChain: (chain) => {
      chain.output.uniqueName('portadesign_data_quality_bundle')
    }
  },
  plugins: [
    pluginGenerateEntrypoints(),
    pluginReact(),
    pluginModuleFederation({
      name: 'portadesign_data_quality_bundle',
      filename: 'static/js/remoteEntry.js',
      exposes: {
        '.': './js/src/plugins.ts'
      },
      dts: false,
      remotes: {
        '@pimcore/studio-ui-bundle': createDynamicRemote('pimcore_studio_ui_bundle')
      },
      shared: {
        ...packages.dependencies,
        react: {
          singleton: true,
          eager: true,
          requiredVersion: false
        },
        'react-dom': {
          singleton: true,
          eager: true,
          requiredVersion: false
        },
        antd: {
          singleton: true,
          requiredVersion: false
        },
        // Keeps this remote from bundling its own copy of the i18next/react-i18next *library*
        // code alongside Studio UI's — see the AI bundle's rsbuild.config.ts for the full
        // explanation of why this alone doesn't solve translation-instance mismatches (it
        // doesn't apply here since this tab has no translation keys, but kept consistent with
        // the AI bundle's federation config so the two remotes don't fight over module versions).
        i18next: {
          singleton: true,
          requiredVersion: false
        },
        'react-i18next': {
          singleton: true,
          requiredVersion: false
        }
      }
    })
  ]
})
