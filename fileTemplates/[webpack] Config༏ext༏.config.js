"use strict"

const argv = require('yargs').argv,
	glob = require('glob'),
	path = require('path');

// Get files
let directories = argv.directory,
	entry = {},
	ignore = {};

// Directories
if (directories) {
	if (!Array.isArray(directories)) {
		directories = [argv.directory];
	}
} else {
	directories = ['./'];
}
directories.forEach((directory) => {
	let files, clean = directory.replace(/^\.\//, '');

	// Javascript
	files = glob.sync('./' + clean + '/**/*.es6');
	if (files.length > 0) {
		files.forEach((file) => {
			let value = file.replace(/^\.\//, ''),
				key = value.replace('.es6', '');

			if (!value.match(/^src\/js\/modules/)) {
				if (!entry.js) entry.js = {};
				if (!entry.js.development) entry.js.development = {};
				if (!entry.js.production) entry.js.production = {};
				entry.js.development[key] = './' + value;
				entry.js.production[key + '.min'] = './' + value;
			}
		});
	}

	// StyleSheet
	files = glob.sync('./' + clean + '/**/*.+(scss|less)');
	if (files.length > 0) {
		files.forEach((file) => {
			let value = file.replace(/^\.\//, ''),
				key = value.replace(/.scss|.less/, '');

			if (!value.match(/^node_modules/)) {
				if (!entry.css) entry.css = {};
				if (!entry.css.development) entry.css.development = {};
				if (!entry.css.production) entry.css.production = {};
				entry.css.development[key] = './' + value;
				entry.css.production[key + '.min'] = './' + value;

				if (!ignore.emit) ignore.emit = {};
				if (!ignore.emit.css) ignore.emit.css = {};
				if (!ignore.emit.css.development) ignore.emit.css.development = [];
				if (!ignore.emit.css.production) ignore.emit.css.production = [];
				ignore.emit.css.development.push(key + '.js');
				ignore.emit.css.production.push(key + '.min.js');
			}
		});
	}

	// Image
	files = glob.sync('./' + clean + '/**/*.+(svg|png|jpg|jpeg|gif)');
	if (files.length > 0) {
		files.forEach((file) => {
			let value = file.replace(/^\.\//, ''),
				key = value;

			if (!value.match(/^node_modules/)) {
				if (!entry.image) entry.image = {};
				entry.image[key] = './' + value;

				if (!ignore.emit) ignore.emit = {};
				if (!ignore.emit.image) ignore.emit.image = [];
				ignore.emit.image.push(key + '.js');
			}
		});
	}

	// Sprite
	files = glob.sync('./' + clean + '/**/*.sprite/*.svg');
	if (files.length > 0) {
		files.forEach((file) => {
			let value = path.dirname(file).replace(/^\.\//, ''),
				key = value.replace('.sprite', '')

			if (!value.match(/^node_modules/)) {
				if (!entry.sprite) entry.sprite = {};
				entry.sprite[key] = value;
			}
		});
	}
});


// Prepare configs
if (Object.keys(entry).length === 0) throw 'Files not founds';
const configs = [],
	CSSOWebpackPlugin = require('csso-webpack-plugin').default,
	IgnoreEmitPlugin = require('ignore-emit-webpack-plugin'),
	MiniCssExtractPlugin = require('mini-css-extract-plugin'),
	SpriteLoaderPlugin = require('svg-sprite-loader/plugin'),
	TerserPlugin = require('terser-webpack-plugin');

// Javascript
if (entry.js) {
	// Development
	configs.push({
		mode: 'development',
		name: 'Javascript Development',
		devtool: 'source-map',
		entry: entry.js.development,
		output: {
			filename: '[name].js',
			path: path.resolve(__dirname)
		},
		module: {
			rules: [
				{
					test: /\.es6$/,
					use: [{loader: 'babel-loader'}]
				}
			]
		}
	});

	// Production
	configs.push({
		mode: 'production',
		name: 'Javascript Production',
		entry: entry.js.production,
		output: {
			filename: '[name].js',
			path: path.resolve(__dirname)
		},
		module: {
			rules: [
				{
					test: /\.es6$/,
					use: [{loader: 'babel-loader'}]
				}
			]
		},
		optimization: {
			minimize: true,
			minimizer: [
				new TerserPlugin({
					terserOptions: {
						output: {
							comments: false,
						},
					},
					extractComments: false,
				}),
			],
		}
	});
}

// StyleSheet
if (entry.css) {
	// Development
	configs.push({
		mode: 'development',
		name: 'StyleSheet Development',
		entry: entry.css.development,
		devtool: 'source-map',
		output: {
			filename: '[name].js',
			path: path.resolve(__dirname)
		},
		module: {
			rules: [
				{
					test: /\.(sa|sc|c)ss$/,
					use: [
						MiniCssExtractPlugin.loader,
						{
							loader: 'css-loader',
							options: {
								sourceMap: true,
							},
						},
						{
							loader: 'sass-loader',
							options: {
								sourceMap: true,
							},
						},
					],
				},
				{
					test: /\.less$/,
					use: [
						MiniCssExtractPlugin.loader,
						{
							loader: 'css-loader',
							options: {
								sourceMap: true,
							},
						},
						{
							loader: 'less-loader',
							options: {
								sourceMap: true,
							},
						},
					],
				},
			]
		},
		plugins: [
			new MiniCssExtractPlugin({
				filename: '[name].css',
				chunkFilename: '[id].css',
			}),
			new IgnoreEmitPlugin(ignore.emit.css.development)
		],
	});

	// Production
	configs.push({
		mode: 'production',
		name: 'StyleSheet Production',
		entry: entry.css.production,
		output: {
			filename: '[name].js',
			path: path.resolve(__dirname)
		},
		optimization: {
			minimize: true
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
				{
					test: /\.less$/,
					use: [
						MiniCssExtractPlugin.loader,
						'css-loader',
						'less-loader',
					],
				},
			]
		},
		plugins: [
			new MiniCssExtractPlugin({
				filename: '[name].css',
				chunkFilename: '[id].css',
			}),
			new CSSOWebpackPlugin({}),
			new IgnoreEmitPlugin(ignore.emit.css.production)
		],
	});
}

// Image
if (entry.image) {
	configs.push({
		mode: 'production',
		name: 'Image',
		entry: entry.image,
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
			new IgnoreEmitPlugin(ignore.emit.image)
		],
	});
}

// Sprite
if (entry.sprite) {
	Object.keys(entry.sprite).forEach((key) => {
		let value = entry.sprite[key];
		configs.push({
			mode: 'production',
			name: 'Sprite ' + key,
			devtool: 'source-map',
			entry: {
				sprite: glob.sync(path.resolve(__dirname, value + '/*.svg')),
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
						include: path.resolve(__dirname, value),
						options: {
							extract: true,
							spriteFilename: key + '.svg',
						},
					},
				],
			},
			plugins: [
				new SpriteLoaderPlugin(),
				new IgnoreEmitPlugin([key + '.js', 'sprite.js'])
			]
		})
	});
}

// Add configs
module.exports = configs;