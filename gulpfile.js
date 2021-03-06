'use strict';

var gulp         = require('gulp');
var del          = require('del');
var rename       = require('gulp-rename');
var sourcemaps   = require('gulp-sourcemaps');
var uglify       = require('gulp-uglify');
var prune        = require('gulp-prune');
var newer        = require('gulp-newer');
var imagemin     = require('gulp-imagemin');

gulp.task('clean:js', function() {
	return del(['assets/*.js', 'assets/*.js.map']);
});

gulp.task('clean:img', function() {
	return del(['assets/*.png']);
});

gulp.task('clean', gulp.series(['clean:js', 'clean:img']));

gulp.task('img', function() {
	var dest = 'assets/';
	return gulp.src(['assets-dev/*.png'])
		.pipe(prune({ dest: dest, ext: ['.png'] }))
		.pipe(newer({ dest: dest }))
		.pipe(imagemin([
			imagemin.optipng({ optimizationLevel: 9 })
		]))
		.pipe(gulp.dest('assets'))
	;
});

gulp.task('js', function() {
	var src  = ['assets-dev/*.js'];
	var dest = 'assets/';
	return gulp.src(src)
		.pipe(prune({
			dest: dest,
			ext: ['.min.js.map', '.min.js']
		}))
		.pipe(newer({
			dest: dest,
			ext: '.min.js'
		}))
		.pipe(sourcemaps.init())
		.pipe(uglify())
		.pipe(rename({suffix: '.min'}))
		.pipe(sourcemaps.write('.'))
		.pipe(gulp.dest(dest))
	;
});

gulp.task('default', gulp.parallel(['img', 'js']));
