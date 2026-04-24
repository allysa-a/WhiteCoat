import path from 'path'
import { defineConfig, mergeConfig } from 'vite'
import baseConfig from './vite.config'

const deployBase = process.env.VITE_PUBLIC_BASE || './'

export default mergeConfig(
  baseConfig,
  defineConfig({
    base: deployBase,
    build: {
      outDir: path.resolve(__dirname, '..'),
      assetsDir: 'assets',
      emptyOutDir: false,
    },
  })
)
