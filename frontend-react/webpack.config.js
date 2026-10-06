const path = require('path');
const webpack = require('webpack');
const HtmlWebpackPlugin = require('html-webpack-plugin');
const dotenv = require('dotenv');

// Load .env (falls back silently if the file does not exist yet)
const env = dotenv.config({ path: path.resolve(__dirname, '.env') }).parsed || {};

module.exports = (_env, argv) => {
  const isProd = argv.mode === 'production';

  return {
    entry: path.resolve(__dirname, 'src', 'index.jsx'),
    output: {
      path: path.resolve(__dirname, 'dist'),
      filename: isProd ? 'assets/[name].[contenthash].js' : 'assets/[name].js',
      publicPath: '/',
      clean: true,
    },
    mode: isProd ? 'production' : 'development',
    devtool: isProd ? 'source-map' : 'eval-source-map',
    resolve: {
      extensions: ['.js', '.jsx'],
    },
    module: {
      rules: [
        {
          test: /\.jsx?$/,
          exclude: /node_modules/,
          use: 'babel-loader',
        },
        {
          test: /\.css$/,
          use: ['style-loader', 'css-loader'],
        },
      ],
    },
    plugins: [
      new HtmlWebpackPlugin({
        template: path.resolve(__dirname, 'public', 'index.html'),
        title: 'EDU-SMART',
      }),
      new webpack.DefinePlugin({
        'process.env.API_BASE_URL': JSON.stringify(env.API_BASE_URL || 'http://localhost:8000/api'),
        'process.env.FIREBASE_API_KEY': JSON.stringify(env.FIREBASE_API_KEY || ''),
        'process.env.FIREBASE_AUTH_DOMAIN': JSON.stringify(env.FIREBASE_AUTH_DOMAIN || ''),
        'process.env.FIREBASE_PROJECT_ID': JSON.stringify(env.FIREBASE_PROJECT_ID || ''),
        'process.env.FIREBASE_STORAGE_BUCKET': JSON.stringify(env.FIREBASE_STORAGE_BUCKET || ''),
        'process.env.FIREBASE_MESSAGING_SENDER_ID': JSON.stringify(env.FIREBASE_MESSAGING_SENDER_ID || ''),
        'process.env.FIREBASE_APP_ID': JSON.stringify(env.FIREBASE_APP_ID || ''),
      }),
    ],
    devServer: {
      port: 5173,
      historyApiFallback: true,
      open: false,
      hot: true,
    },
    performance: { hints: false },
  };
};
