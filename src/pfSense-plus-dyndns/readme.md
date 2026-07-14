# pfSense Plus Dynamic DNS 阿里云/腾讯云补丁

本补丁为 pfSense Plus 的 Dynamic DNS 页面增加阿里云 DNS 和腾讯云 DNSPod 支持。

## 支持的服务

- Aliyun DNS
- Aliyun DNS (v6)
- Tencent Cloud DNS
- Tencent Cloud DNS (v6)

## 文件说明

- `src/`：补丁文件，目录结构对应 pfSense Plus 系统路径。
- `install.sh`：安装脚本，会先备份原文件再覆盖补丁。
- `uninstall.sh`：卸载脚本，会从备份目录恢复原文件。
- `check_install.sh`：自愈检查脚本，检测补丁缺失时自动调用 `install.sh`。

涉及的 pfSense Plus 文件：

- `/etc/inc/dyndns.class`
- `/etc/inc/globals.inc`
- `/etc/inc/services.inc`
- `/usr/local/www/services_dyndns.php`
- `/usr/local/www/services_dyndns_edit.php`

## 安装方法

将本目录上传到 pfSense Plus 主机后，用 root 执行：

```sh
chmod +x install.sh uninstall.sh
./install.sh
```

安装时会自动创建备份目录，例如：

```text
/root/dyndns-plus-aliyun-tencent-backup-20260621-090000
```

安装脚本还会把补丁包复制到持久目录：

```text
/root/dyndns-plus-aliyun-tencent
```

并注册开机自愈脚本：

```text
/usr/local/etc/rc.d/dyndns-plus-aliyun-tencent_check
```

开机后会自动执行 `check_install.sh`。如果 pfSense Plus 升级覆盖了相关文件，自愈脚本会检测 `aliyun` 和 `tencentcloud` 是否缺失，缺失时自动重新安装。

安装前会检查目标文件是否仍包含 Dynamic DNS provider 列表、域名拆分列表、更新函数和编辑页显示逻辑等关键结构。如果上游升级后结构变化过大，安装会停止并提示，不会强行覆盖。

## 卸载方法

恢复最新备份：

```sh
./uninstall.sh
```

也可以指定备份目录：

```sh
./uninstall.sh /root/dyndns-plus-aliyun-tencent-backup-20260621-090000
```

## 配置说明

进入 pfSense Plus Web 界面：

```text
Services > Dynamic DNS > Dynamic DNS Clients
```

新增或编辑客户端时选择对应服务。

阿里云：

- `Username`：AccessKey ID
- `Password`：AccessKey Secret
- `Hostname`：主机记录，例如 `www`，根域名填写 `@`
- `Domain name`：域名区域，例如 `example.com`
- `TTL`：可选，默认 600

腾讯云：

- `Username`：SecretId
- `Password`：SecretKey
- `Hostname`：主机记录，例如 `www`，根域名填写 `@`
- `Domain name`：域名区域，例如 `example.com`
- `Zone ID`：可选，填写解析线路；不填时默认使用 `默认`
- `TTL`：可选，默认 600

### 腾讯云 IPv6 说明

`Tencent Cloud DNS (v6)` 会更新 AAAA 记录，但 DNSPod 的腾讯云 API 域名 `dnspod.tencentcloudapi.com`
当前只提供 IPv4 访问入口。补丁已默认让 `tencentcloud-v6` 通过 IPv4 访问 API，同时仍然使用
WAN 的 IPv6 地址更新 AAAA 记录。这样可以避免缓存停留在 `::` 或页面显示 `N/A`。

如果 pfSense 上安装了 Mihomo/Clash 并启用了 fake-ip，请确保以下域名直连且不进入 fake-ip：

```text
dnspod.tencentcloudapi.com
+.tencentcloudapi.com
+.dnspod.cn
+.dnspod.com
```

### 状态显示排错

- 页面显示 `N/A`：通常表示没有成功写入 DDNS 缓存。请确认 API 密钥权限、DNSPod 记录是否存在，以及
  pfSense 本机能访问 `https://dnspod.tencentcloudapi.com/`。
- 页面显示红色但外部 AAAA 已正确：通常是 pfSense 本机状态检查取不到接口 IPv6。补丁状态页会在
  `dyndnsCheckIP()` 失败时回退到接口 IPv6 进行比较。
- 如果 WAN IPv6 网关监控没有 online，建议在 `System > Routing > Gateways` 给 IPv6 网关设置一个
  可达的 Monitor IP，例如 `2400:3200::1`。

## 注意事项

- 安装脚本必须在 pfSense Plus 主机上以 root 用户运行。
- pfSense Plus 升级可能覆盖这些文件，升级后需要重新安装补丁。
- 已安装自愈脚本时，开机后会自动检测并尝试恢复补丁。
- API 密钥需要具备对应 DNS 记录的查询、新增和修改权限。
