# pfSense 静态绑定插件

`pfSense-pkg-arp.pkg` 为 pfSense 提供“服务 > 静态绑定”页面，用于维护 ARP 静态 IP/MAC 绑定记录。

## 功能

- 查看当前接口的 ARP 应答模式。
- 查看系统当前学习到的 ARP 表。
- 将当前 ARP 表复制为绑定记录。
- 保存并应用静态 ARP 绑定配置。
- 生成客户端 ARP 绑定脚本。
- 支持多语言显示：
  - 英文：`en_US`
  - 简体中文：`zh_CN`、`zh_Hans_CN`
  - 繁体中文：`zh_TW`、`zh_Hant_TW`
  - 其他语言默认显示英文。

## 标准包结构

本项目按 pfSense 标准插件包方式组织文件，只安装自身文件，不覆盖 pfSense 系统 WebGUI 文件。

主要文件：

- `src/usr/local/www/services_arp.php`：WebGUI 页面。
- `src/usr/local/pkg/staticarp.xml`：pfSense 插件菜单与钩子注册文件。
- `src/usr/local/pkg/staticarp.inc`：安装、卸载、重同步逻辑。
- `src/usr/local/share/pfSense-pkg-staticarp/info.xml`：pfSense 插件信息文件。
- `packaging/freebsd/`：FreeBSD pkg 元数据脚本。

不会覆盖以下 pfSense 系统文件：

- `/usr/local/www/head.inc`
- `/usr/local/www/guiconfig.inc`

## 编译

请在 FreeBSD 或 pfSense 主机上编译，需系统可用 `pkg` 命令：

```sh
make package
```

编译完成后生成：

```text
dist/pfSense-pkg-arp.pkg
```

## 安装

将包上传到 pfSense 后执行：

```sh
pkg add -f pfSense-pkg-arp.pkg
```

安装完成后刷新 WebGUI，进入：

```text
服务 > 静态绑定
```

英文界面中对应菜单为：

```text
Services > Static Binding
```

## 使用说明

1. 在“当前 ARP 表”中确认系统已学习到需要绑定的客户端。
2. 点击“复制当前 ARP 表”，将记录复制到“绑定记录”。
3. 删除不需要固定绑定的记录。
4. 确认管理主机已在绑定记录中，避免启用后失去 WebGUI 访问。
5. 勾选“启用静态 ARP 绑定”并保存。

绑定记录格式为每行一条：

```text
192.168.10.10 00:11:22:33:44:55
```

## ARP 模式

- 正常应答：接口按系统默认方式响应 ARP 请求。
- 静态应答：仅响应静态 ARP 表中的客户端，适合 LAN 侧固定终端。
- 取消应答：接口不响应 ARP 请求，除非明确需要隔离，否则请谨慎使用。

## 注意事项

- 启用绑定前，请务必确认管理电脑已加入绑定记录。
- 建议优先只在 LAN 接口使用静态应答。
- 升级或重新安装 pfSense 后，如插件菜单未显示，可重新安装本插件包。
