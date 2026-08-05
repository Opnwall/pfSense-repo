# pfSense DNSCrypt Proxy 软件包

[简体中文](README.zh_CN.md) | [English](README.md)

[![CI](https://github.com/nopoz/pfsense-dnscrypt-proxy/actions/workflows/ci.yml/badge.svg)](https://github.com/nopoz/pfsense-dnscrypt-proxy/actions/workflows/ci.yml)
[![Latest release](https://img.shields.io/github/v/release/nopoz/pfsense-dnscrypt-proxy?sort=semver)](https://github.com/nopoz/pfsense-dnscrypt-proxy/releases/latest)
[![Build provenance](https://img.shields.io/badge/build%20provenance-attested-success)](SECURITY.md#verifying-a-download)
[![License: ISC](https://img.shields.io/badge/license-ISC-blue.svg)](LICENSE)

这是一个为 pfSense 提供完整 WebGUI 的 DNSCrypt Proxy 软件包，支持
DNSCrypt v2、DNS-over-HTTPS（DoH）、Oblivious DoH（ODoH）和匿名 DNS。

> 项目来源：本项目基于
> [nopoz/pfsense-dnscrypt-proxy](https://github.com/nopoz/pfsense-dnscrypt-proxy)
> 修改，遵循原项目 ISC 许可证。
> 添加 WebGUI 多语言支持以及 Opnwall pfSense 软件仓库支持。本项目为社区维护项目，
> 与 Netgate 无隶属或官方支持关系。

## 功能

- 8 个 WebGUI 标签页，覆盖常规设置、服务器选择、缓存与过滤、日志、列表、高级设置、查询日志和配置管理
- 支持 DNSCrypt v2、DoH、ODoH 和带中继路由的匿名 DNS
- 内置 Cloudflare、Quad9、Google、AdGuard、NextDNS、Mullvad、OpenDNS、CleanBrowsing 等常用提供商
- 支持 DNS Stamp 自定义解析器和原始 TOML 高级选项
- 支持阻止/允许列表、转发规则、伪装规则和查询日志查看器
- 同时包含 amd64 和 arm64 二进制文件，并自动选择当前架构
- WebGUI 自动跟随 pfSense 当前语言，支持英文、简体中文和繁体中文
- 作为原生服务出现在“状态 > 服务”中

## 从 Opnwall 仓库安装（推荐）

先按照 [Opnwall pfSense Repo](https://opnwall.github.io/pfSense-repo/)
的说明启用软件仓库，然后进入：

**系统 > 软件包管理器 > 可用软件包**

搜索并安装 `dnscrypt-proxy`。通过仓库安装后，可以正常发现并升级后续版本。

## 直接安装

pfSense CE：

```sh
pkg-static add https://github.com/nopoz/pfsense-dnscrypt-proxy/releases/latest/download/pfSense-pkg-dnscrypt-proxy.pkg
```

pfSense Plus：

```sh
pkg-static -C /dev/null add https://github.com/nopoz/pfsense-dnscrypt-proxy/releases/latest/download/pfSense-pkg-dnscrypt-proxy.pkg
```

安装完成后，进入 **服务 > DNSCrypt Proxy**。

## 基本配置

1. 打开 **服务 > DNSCrypt Proxy**。
2. 勾选 **启用 DNSCrypt Proxy 服务**。
3. 在 **服务器选择** 中选择所需的服务器。
4. 保存设置。

推荐让 Unbound 将查询转发到 DNSCrypt Proxy。在
**服务 > DNS 解析器 > 常规设置 > 自定义选项** 中加入：

```text
server:
    do-not-query-localhost: no
forward-zone:
    name: "."
    forward-addr: 127.0.0.1@5300
```

保存并应用更改。若要直接作为系统 DNS 使用，请先停用占用 53 端口的 DNS
解析器，将 DNSCrypt Proxy 监听端口改为 `53`，再把系统 DNS 设置为
`127.0.0.1`。

## 升级与卸载

从 Opnwall 仓库安装的版本可直接使用 pfSense 软件包管理器升级。

卸载：

```sh
pkg delete pfSense-pkg-dnscrypt-proxy
```

升级时保留 `config.xml` 中的用户设置。正常卸载会删除插件设置、运行文件、菜单和
服务登记，避免在“已安装插件”页面留下红色感叹号的孤立记录。

## 从源码构建

需要 FreeBSD 的 `pkg` 工具，或者一台可通过 SSH 访问的 pfSense：

```sh
git clone https://github.com/nopoz/pfsense-dnscrypt-proxy.git
cd pfsense-dnscrypt-proxy
./build.sh build
```

实机远程构建和安装：

```sh
./build.sh deploy pfsense.local
```

## 安全与支持

软件包内置的 DNSCrypt Proxy 二进制来自上游发布，原项目自动更新流程会验证
官方 minisign 签名；发布工作流同时生成 SHA256 校验和与构建来源证明。

问题反馈和原始项目讨论：

- [nopoz/pfsense-dnscrypt-proxy Issues](https://github.com/nopoz/pfsense-dnscrypt-proxy/issues)
- [DNSCrypt/dnscrypt-proxy](https://github.com/DNSCrypt/dnscrypt-proxy)
