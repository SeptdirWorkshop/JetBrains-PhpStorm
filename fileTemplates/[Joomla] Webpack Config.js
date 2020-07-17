"use strict"

const argv = require('yargs').argv,
	glob = require('glob'),
	path = require('path');

// Get files
let directories = argv.directory,
	es6 = {},
	scss = {},
	ignoreSCSSEmits = [],
	images = {},
	ignoreImagesEmits = [],
	sprites = [];

if (directories) {
	if (!Array.isArray(directories)) {
		directories = [argv.directory];
	}
} else {
	directories = ['./'];
}
directories.forEach((directory) => {
	let clean = directory.replace(/^\.\//, '');

	// ES6
	let es6Files = glob.sync('./' + clean + '/**/*.es6');
	if (es6Files.length > 0) {
		es6Files.forEach((file) => {

			let value = file.replace(/^\.\//, ''),
				key = value.replace('.es6', '');
			if (!value.match(/^src\//)) {
				es6[key] = './' + value;
				es6[key + '.min'] = './' + value;
			}
		});
	}


	// SCSS
	let scssFiles = glob.sync('./' + clean + '/**/*.scss');
	if (scssFiles.length > 0) {
		scssFiles.forEach((file) => {
			let value = file.replace(/^\.\//, ''),
				key = value.replace('.scss', '');
			if (!value.match(/^src\//)) {
				scss[key] = './' + value;
				ignoreSCSSEmits.push(key + '.js');
			}
		});
	}

	// Images
	let imagesFiles = glob.sync('./' + clean + '/**/*.+(svg|png|jpg|jpeg|gif)');
	if (imagesFiles.length > 0) {
		imagesFiles.forEach((file) => {
			let value = file.replace(/^\.\//, ''),
				key = value;
			if (!value.match(/^src\//)) {
				images[key] = './' + value;
				ignoreImagesEmits.push(key + '.js');
			}
		});
	}

	// Sprites
	let spritesFiles = glob.sync('./' + clean + '/**/*.sprite/*.svg');
	if (spritesFiles.length > 0) {
		spritesFiles.forEach((file) => {
			let sprite = path.dirname(file).replace(/^\.\//, '').replace('.sprite', '')

			if (!sprite.match(/^src\//) && sprites.indexOf(sprite) === -1) {
				sprites.push(sprite)
			}
		});
	}
});

// Add to export
const TerserPlugin = require('terser-webpack-plugin'),
	CssoWebpackPlugin = require('csso-webpack-plugin').default,
	MiniCssExtractPlugin = require('mini-css-extract-plugin'),
	IgnoreEmitPlugin = require('ignore-emit-webpack-plugin'),
	SpriteLoaderPlugin = require('svg-sprite-loader/plugin');
module.exports = [];
if (Object.keys(es6).length > 0) {
	module.exports.push({
		mode: 'production',
		name: 'javascript',
		entry: es6,
		output: {
			filename: '[name].js',
			path: path.resolve(__dirname)
		},
		optimization: {
			minimize: true,
			minimizer: [
				new TerserPlugin({
					include: /\.min\.js$/,
					terserOptions: {
						output: {
							comments: false,
						},
					},
					extractComments: false,
				}),
			],
		},
		module: {
			rules: [
				{
					test: /\.es6$/,
					use:
						{
							loader: 'babel-loader',
							options: {presets: [["@babel/preset-env"]]}
						}
				}
			]
		}
	});
}
if (Object.keys(scss).length > 0) {
	module.exports.push({
		mode: 'development',
		name: 'stylesheet',
		entry: scss,
		output: {
			filename: '[name].js',
			path: path.resolve(__dirname)
		},
		optimization: {
			minimize: false
		},
		module: {
			rules: [
				{
					test: /\.(sa|sc|c)ss$/,
					use: [
						MiniCssExtractPlugin.loader,
						'css-loader',
						'sass-loader'
					],
				},
			]
		},
		plugins: [
			new MiniCssExtractPlugin({
				filename: '[name].css',
				chunkFilename: '[id].css',
			}),
			new CssoWebpackPlugin({
				pluginOutputPostfix: 'min'
			}),
			new IgnoreEmitPlugin(ignoreSCSSEmits)
		],
	});
}
if (Object.keys(images).length > 0) {
	module.exports.push({
		mode: 'production',
		name: 'images',
		entry: images,
		output: {
			filename: '[name].js',
			path: path.resolve(__dirname)
		},
		module: {
			rules: [
				{
					test: /\.(gif|png|jpe?g|svg)$/i,
					use: [
						'file-loader?name=[path][name].[ext]',
						{
							loader: 'image-webpack-loader',
							options: {}
						},
					],
				}
			],
		},
		plugins: [
			new IgnoreEmitPlugin(ignoreImagesEmits)
		],
	});
}
if (sprites.length > 0) {
	sprites.forEach((sprite, i) => {
		module.exports.push(
			{
				mode: 'production',
				name: 'sprite_' + i,
				entry: {
					sprite: glob.sync(path.resolve(__dirname, sprite + '.sprite/*.svg')),
				},
				output: {
					filename: '[name].js',
					path: path.resolve(__dirname)
				},
				module: {
					rules: [
						{
							test: /\.svg$/,
							loader: 'svg-sprite-loader',
							include: path.resolve(__dirname, sprite + '.sprite'),
							options: {
								extract: true,
								spriteFilename: sprite + '.svg',
							},
						},
					],
				},
				plugins: [
					new SpriteLoaderPlugin(),
					new IgnoreEmitPlugin([sprite + '.js', 'sprite.js'])
				],
			});
	});
}