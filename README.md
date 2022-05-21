# Laravel集成Vite

`Vite`（法语意为“快速的”，发音`/vit/`，发音同 `veet`）是一种新型前端构建工具，能够显著提升前端开发体验。它主要由两部分组成：

* 一个开发服务器，它基于 [原生 ES](https://developer.mozilla.org/en-US/docs/Web/JavaScript/Guide/Modules) 模块 提供了 [丰富的内建功能](https://cn.vitejs.dev/guide/features.html)，如速度快到惊人的 [模块热更新（HMR）](https://cn.vitejs.dev/guide/features.html#hot-module-replacement)。

* 一套构建指令，它使用 [Rollup](https://rollupjs.org/) 打包你的代码，并且它是预配置的，可输出用于生产环境的高度优化过的静态资源。

`Vite` 意在提供开箱即用的配置，同时它的 [插件 API](https://cn.vitejs.dev/guide/api-plugin.html) 和 [JavaScript API](https://cn.vitejs.dev/guide/api-javascript.html) 带来了高度的可扩展性，并有完整的类型支持。

本文主要探讨`Laravel`集成`Vite`的基本配置，在开始之前，你可以考虑要不要自己配置它。[innocenzi/laravel-vite](https://laravel-vite.netlify.app/)是一个现成的解决方案，可以将`Vite`添加到您的`Laravel`应用程序中。如果您更喜欢完全拥有自己的构建工具（如我），或者想了解更多关于内部实现的细节，请继续往下阅读。

## 安装

首先移除`package.json`文件中默认的`Laravel Mix`依赖项：

```json
{
    "private": true,
    "scripts": {
-        "dev": "npm run development",
-        "development": "mix",
-        "watch": "mix watch",
-        "watch-poll": "mix watch -- --watch-options-poll=1000",
-        "hot": "mix watch --hot",
-        "prod": "npm run production",
-        "production": "mix --production"
    },
    "devDependencies": {
        "axios": "^0.21",
-        "laravel-mix": "^6.0.6",
        "lodash": "^4.17.19",
-        "postcss": "^8.1.14"
    }
}
```

接下来，安装`Vite`依赖：

```shell
$ npm install vite -D
```

回到`package.json`文件，添加如下内容：

```json
{
    "private": true,
    "scripts": {
+      "dev": "vite",
+      "production": "vite build"
    },
    "devDependencies": {
        "axios": "^0.21",
        "lodash": "^4.17.19",
        "vite": "^2.9.9"
    }
}
```

如果您的开发环境使用`https`，`dev`脚本可以用`vite --https`。

## Vite配置

在项目根目录下创建`vite.config.js`文件，添加以下内容：

```javascript
// vite.config.js
export default ({ command }) => ({
  base: command === 'serve' ? '' : '/build/',
  build: {
    manifest: true,
    outDir: 'public/build',
    rollupOptions: {
      input: 'resources/js/app.js',
    },
  },
})
```

参数说明：

* `build.rollupOptions.input`：打包指定的入口文件；
* `build.manifest`：生成一个`manifest.json`文件；
* `build.outDir`：构建输出目录，在`Laravel`项目中，它必须在`public`目录下，这里设置为`public/build`，方便添加到`.gitignore`；
* `base`: 因为我们修改了`build.outDir`，所以需要配置`base`。这确保`Vite`生成的文件中所有路径引用指向`/build/`目录，因为在开发环境中`command === 'server'`上，静态资源文件从`http://localhost:3000`读取，`build.outDir`没有被使用，因此不应该覆盖`base`。

接下来，我们需要对默认的`app.js`文件进行一些修改：

* 由于`Vite`要求使用`ES`模块，所以要将所有的`require`命令替换为`import`。

```javascript
// resources/js/app.js
import './bootstrap'

import '../css/app.css'

// resources/js/bootstrap.js
+ import _ from 'lodash';
+ import axios from 'axios';

window._ = _

/**
 * We'll load the axios HTTP library which allows us to easily issue requests
 * to our Laravel back-end. This library automatically handles sending the
 * CSRF token as a header based on the value of the "XSRF" token cookie.
 */

window.axios = axios

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
```

至此，`Vite`配置完成，开发环境和正式环境分别运行`npm run dev`，通过`http://localhost:3000`访问。运行`npm run production`将会生成`public/build`目录。

## Laravel配置

前端服务构建完后，这时我们需要在项目中加载静态资源文件。

首先是开发环境，当我们运行`npm run dev`，`Vite`启动了本地服务`http://localhost:3000`，因此需要在`blade`页面添加以下`script`标签：

```html
<script type="module" src="http://localhost:3000/@vite/client"></script>
<script type="module" src="http://localhost:3000/resources/js/app.js"></script>
```

当运行`npm run production`时，我们需要读取`public/build/manifest.json`文件，找到确切的静态资源文件路径，这就需要以下几个步骤：

* 读取`manifest.json`文件
* 提取`js`文件位置并渲染`script`元素
* 提取`css`文件并渲染`link`元素

```
@php
    $manifest = json_decode(file_get_contents(public_path('build/manifest.json')), true);
@endphp
<script type="module" src="/build/{{$manifest['resources/js/app.js']['file']}}"></script>
<link rel="stylesheet" href="/build/{{$manifest['resources/js/app.js']['css'][0]}}">
```

这时我们可以将在`Blade`模板中编写的代码提取到一个辅助函数中：

```php
if (!function_exists('vite_assets')) {
    /**
     * @return bool
     */
    function vite_assets(): bool
    {
        $devServerIsRunning = false;

        if (app()->environment('local')) {
            try {
                Http::get("http://localhost:3000");
                $devServerIsRunning = true;
            } catch (\Exception $ex) {
                Log::error($ex->getMessage());
            }
        }

        if ($devServerIsRunning) {
            return new HtmlString(<<<HTML
            <script type="module" src="http://localhost:3000/@vite/client"></script>
            <script type="module" src="http://localhost:3000/resources/js/app.js"></script>
        HTML);
        }

        $manifest = json_decode(file_get_contents(
            public_path('build/manifest.json')
        ), true);

        return new HtmlString(<<<HTML
        <script type="module" src="/build/{$manifest['resources/js/app.js']['file']}"></script>
        <link rel="stylesheet" href="/build/{$manifest['resources/js/app.js']['css'][0]}">
    HTML);
    }
}
```

最后，在`blade`模板中直接调用该函数即可：

```php
{{ vite_assets() }}
```

文中代码已上传[github](https://github.com/trumanwong/laravel-vite-example)。