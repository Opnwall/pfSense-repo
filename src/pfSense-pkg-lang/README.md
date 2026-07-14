# 汉化工具 for pfSense

这是一个 pfSense WebGUI 汉化补丁安装器。安装后会在 `System > 汉化补丁` 添加页面，管理员可从受信任的 `cloud.pfchina.org` 下载对应版本的语言包并应用。

## 安装

```sh
pkg add -f pfSense-pkg-lang.pkg
```

或在项目目录中使用辅助脚本：

```sh
./install.sh dist/pfSense-pkg-lang.pkg
```

## 卸载

```sh
pkg delete -y pfSense-pkg-lang
```

或：

```sh
./uninstall.sh
```

## 打包

打包需要在 FreeBSD/pfSense 环境运行，因为依赖 `pkg create`。

```sh
make package
```

生成文件位于 `dist/pfSense-pkg-lang.pkg`。

## 语言列表

`src/var/lang/list` 使用兼容旧版的格式：

```text
显示名称='https://cloud.pfchina.org/path/to/lang.zip'
```

条目也可以可选附带 SHA256：

```text
显示名称='https://cloud.pfchina.org/path/to/lang.zip' sha256=<64位sha256>
```

如果条目提供 SHA256，工具会在安装语言包前校验下载文件；如果没有提供，则跳过哈希校验。语言包内仍必须包含 `etc/version`，且其版本号必须与当前 pfSense 版本号精确匹配。
