<div align="center">
  <a href="README.md">中文</a> |
  <a href="README.US.md">English</a>
</div>

# pfSense Community Repository

![pfSense CE](https://img.shields.io/badge/pfSense_CE-005AA0?logo=pfsense&logoColor=white)
![pfSense Plus](https://img.shields.io/badge/pfSense_Plus-00A86B)
![FreeBSD](https://img.shields.io/badge/FreeBSD-15%20%7C%2016-red?logo=freebsd)
![amd64](https://img.shields.io/badge/Architecture-amd64-success)
![License](https://img.shields.io/badge/License-Community-blue)

pfSense CE 与 pfSense Plus 社区软件仓库，提供一系列开源、高质量的第三方插件，可通过系统原生 `pkg` 包管理器安装。

## 支持平台

| 系统 | 版本 | ABI | PHP | 仓库状态 |
| --- | --- | --- | --- | --- |
| pfSense CE | 2.8.1 | `FreeBSD:15:amd64` | 8.3 | 已测试 |
| pfSense Plus | 26.03.1 | `FreeBSD:16:amd64` | 8.5 | 已测试 |

## 安装方法

### pfSense CE：

```sh
fetch -o /usr/local/etc/pkg/repos/opnwall.conf \
  https://opnwall.github.io/pfSense-repo/pfsense-ce-opnwall.conf
pkg update -f
```

### pfSense Plus：

```sh
fetch -o /usr/local/etc/pkg/repos/opnwall.conf \
  https://opnwall.github.io/pfSense-repo/pfsense-plus-opnwall.conf
pkg update -f
```

使用 `pkg search pfSense-pkg-` 查看插件，使用 `pkg install <软件包名>` 安装。

### 补丁安装

pfSense 默认只在官方仓库中查询 `pfSense-pkg-*`，并隐藏来源不是官方仓库的已安装插件。执行以下命令，让“可用软件包”和“已安装的软件包”同时显示所有已启用仓库中的插件：

```sh
fetch -qo - https://opnwall.github.io/pfSense-repo/enable-opnwall-gui.sh | sh
```

脚本会备份 `/etc/inc/pkg-utils.inc`、验证修改结果并刷新 WebGUI。pfSense 固件更新可能覆盖该修改，升级后可重新执行相同命令。

## 插件列表

| 软件包 | 版本 | 描述 |
| --- | --- | --- |
| `pfSense-pkg-adguardhome` | 1.0.1 | AdGuard Home DNS 过滤集成 |
| `pfSense-pkg-arp` | 1.0.1 | 静态 IP/MAC 绑定 |
| `pfSense-pkg-ddns-go` | 1.0.1 | DDNS-Go 动态 DNS 集成 |
| `pfSense-pkg-lang` | 1.0.1 | 中文汉化工具 |
| `pfSense-pkg-lantest` | 1.0.1 | LanTest 局域网测速工具 |
| `pfSense-pkg-lucky` | 1.0.1 | Lucky 网络工具箱 |
| `pfSense-pkg-mihomo` | 1.0.1 | Mihomo 代理集成 |
| `pfSense-pkg-sing-box` | 1.0.1 | sing-box 代理集成 |
| `pfSense-pkg-speedtest` | 1.0.1 | Speedtest 互联网测速工具 |
| `pfSense-pkg-ttyd` | 1.0.1 | ttyd 网页终端 |


## 安装插件

通过系统包管理器安装插件，安装补丁后，可在插件列表查看所有社区插件。

## 删除仓库

```sh
rm -f /usr/local/etc/pkg/repos/opnwall.conf
pkg update -f
```

删除仓库操作不会卸载已经安装的插件。

## 插件源码

完整源码位于 [`src/`](src/)；每个 `pfSense-pkg-*` 目录均为独立插件项目。可在 pfSense/FreeBSD 主机上使用项目内的 `build.sh` 进行编译。

## 免责声明

本仓库与 Netgate 或 pfSense 项目无隶属关系，也不受其官方支持。第三方软件包可能影响系统升级和稳定性，安装前请备份配置并优先在非生产环境测试。各项目及捆绑组件分别遵循其附带的许可证与声明。
