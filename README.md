# Opnwall pfSense Community Repository

面向 pfSense CE 与 pfSense Plus amd64 系统的非官方社区插件仓库。

## 支持平台

| 系统 | 版本 | ABI | PHP | 仓库状态 |
| --- | --- | --- | --- | --- |
| pfSense CE | 2.8.1 | `FreeBSD:15:amd64` | 8.3 | 已测试 |
| pfSense Plus | 26.03.1 | `FreeBSD:16:amd64` | 8.5 | 已测试 |

## 安装

pfSense CE：

```sh
fetch -o /usr/local/etc/pkg/repos/opnwall.conf \
  https://opnwall.github.io/pfSense-repo/pfsense-ce-opnwall.conf
pkg update -f
```

pfSense Plus：

```sh
fetch -o /usr/local/etc/pkg/repos/opnwall.conf \
  https://opnwall.github.io/pfSense-repo/pfsense-plus-opnwall.conf
pkg update -f
```

使用 `pkg search pfSense-pkg-` 查看插件，使用 `pkg install <软件包名>` 安装。

### 在 WebGUI 插件列表显示社区插件

pfSense 默认只在官方仓库中查询 `pfSense-pkg-*`。执行以下一条命令，可以让“系统 > 软件包管理器 > 可用软件包”查询所有已启用仓库：

```sh
fetch -qo - https://opnwall.github.io/pfSense-repo/enable-opnwall-gui.sh | sh
```

脚本会备份 `/etc/inc/pkg-utils.inc`、验证修改结果并刷新 WebGUI。pfSense 固件更新可能覆盖该修改，升级后可重新执行相同命令。

## 插件

| 软件包 | 版本 | 说明 |
| --- | --- | --- |
| `pfSense-pkg-adguardhome` | 1.0.1 | AdGuard Home DNS 过滤集成 |
| `pfSense-pkg-arp` | 1.0.1 | 静态 IP/MAC 绑定 |
| `pfSense-pkg-ddns-go` | 1.0.1 | DDNS-Go 动态 DNS 集成 |
| `pfSense-pkg-lang` | 1.0.1 | 中文本地化更新工具 |
| `pfSense-pkg-lucky` | 1.0.1 | Lucky 网络工具箱 |
| `pfSense-pkg-mihomo` | 1.0.1 | Mihomo 代理集成 |
| `pfSense-pkg-sing-box` | 1.0.1 | sing-box 代理集成 |
| `pfSense-pkg-ttyd` | 1.0.1 | ttyd 网页终端 |

## 删除仓库

```sh
rm -f /usr/local/etc/pkg/repos/opnwall.conf
pkg update -f
```

该操作不会卸载已经安装的插件。

## 源码

完整源码位于 [`src/`](src/)；每个 `pfSense-pkg-*` 目录均为独立插件项目。`pfSense-ce-dyndns` 与 `pfSense-plus-dyndns` 是平台专用系统补丁源码，不作为普通仓库插件发布。

## 免责声明

本仓库与 Netgate 或 pfSense 项目无隶属关系，也不受其官方支持。第三方软件包可能影响系统升级和稳定性，安装前请备份配置并优先在非生产环境测试。

各项目及捆绑组件分别遵循其附带的许可证与声明。
