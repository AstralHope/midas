# MIDAS (Middleware Integrated Diagnostic Analysis System)

> **Midas touch 为中间件运维点石成金**  
> MIDAS 是面向中间件与云原生基础设施的一体化运维诊断与信息展示平台，集成云上（ACK、Pod、SOFAMQ）、云下（Nginx、Tomcat）以及网络与性能诊断小工具，支持自动化数据采集、快速检索、关联跳转与 API/CLI 纯文本访问。

---

## 目录

- [功能模块介绍](#功能模块介绍)
  - [1. 登录与安全鉴权](#1-登录与安全鉴权)
  - [2. 云上信息](#2-云上信息)
  - [3. 云下信息](#3-云下信息)
  - [4. 运维诊断与小工具](#4-运维诊断与小工具)
- [目录架构与数据流](#目录架构与数据流)
- [容器化部署](#容器化部署)
- [API / 命令行自动化调用](#api--命令行自动化调用)
- [后续规划 (Roadmap)](#后续规划-roadmap)

---

## 功能模块介绍

### 1. 登录与安全鉴权
* **统一会话拦截**：全局接入 Session 鉴权，支持未登录自动跳出 iframe 框架重定向至登录页。
* **默认固定账号**：
  * 用户名：`midadmin`
  * 密　码：`uspOB%0331`
* **双模式认证**：同时支持 Web 界面登录和 **HTTP Basic Auth (`curl -u`)**，无缝兼容自动化脚本与 API 请求。

### 2. 云上信息
* **节点 Pod 信息 (`findMatchingFilesfornode.php` / `display_csv_new.php`)**：
  * 自动检索并展示各 ACK 集群的节点与 Pod 信息。
  * 支持前端分页、关键字全局模糊搜索、多列排序以及过滤结果导出为 CSV。
  * 点击第四列（主机名/IP）可直接下钻查看对应的 Pod 文本明细（`view_txt.php`）。
* **ACK 集群信息 (`findMatchingFiles.php` / `display_csv_new.php`)**：
  * 展示多云环境下的 ACK 集群清单及元数据。
  * 自动将 Master 节点置顶，方便快速核对集群拓扑。
* **SOFAMQ 信息 (`findMatchingFilesforsofamq.php` / `view_sofamq.php`)**：
  * 动态匹配并展示 SOFAMQ 消息队列集群的详细配置与运行状态。

### 3. 云下信息
* **Nginx 信息 (`display_csv_new.php` / `view_conf.php`)**：
  * 汇聚各 ECS 主机中 Nginx 实例的 IP、运行用户、PID、路径与版本。
  * 支持通过超链接直接查看下挂的 Nginx 配置文件（`.conf`）。
* **Tomcat 信息 (`display_csv_new.php`)**：
  * 展示各主机的 Tomcat 实例配置及部署状态。

### 4. 运维诊断与小工具
* **VSW 网段匹配 (`searchVSW.php`)**：
  * 运维网络排查利器。输入任意 IP 地址，自动进行子网掩码计算与网段匹配，精准定位其所属的虚拟交换机（VSW）、所属云、CIDR 网段及具体起止范围。
* **JavaCore 性能分析 (`jstack-review/`)**：
  * 集成基于浏览器的 Java 线程堆栈（Thread Dump / JStack）可视化分析工具。
  * 支持线程状态统计、死锁检测、阻塞关系图谱展示，快速定位 CPU 飙高和线程卡死问题。
* **Jifa 堆内存深度分析 (`/jifa/`)**：
  * 集成阿里巴巴/Eclipse 开源的 Java 故障诊断平台 **Jifa**（基于 Eclipse MAT 底层能力）。
  * 专用于线上 Java 堆转储文件（Heap Dump / HPROF）、GC 日志与 JFR 文件分析，通过 Nginx 内部反向代理集成（`/jifa/` 路径），**无需额外暴露端口**，持久化数据存放于 `data/jifa`。
* **Nginx-vts 实时监控**：
  * 顶部导航栏动态联动当前访问域名并绑定监控端口（`19913/nginx_status`），一键直达实时流量分析。

---

## 目录架构与数据流

项目采用 **代码与数据分离** 的设计理念，宿主机通过 `data` 目录统一管理采集数据、Jifa 分析数据、采集脚本与历史备份：

```text
deskecc/
├── mid/                    # Web 应用源码（PHP / HTML / JS / CSS）
│   ├── auth.php            # 全局鉴权模块（Session / Basic Auth）
│   ├── login.php           # 登录页面
│   ├── index.php           # 系统主框架
│   ├── display_csv_new.php # 通用 CSV 表格展示引擎（搜索/排序/分页/导出）
│   ├── searchVSW.php       # VSW 网段匹配工具
│   ├── view_txt.php        # 纯文本 / Pod 明细查看
│   ├── view_conf.php       # Nginx 配置文件查看
│   └── jstack-review/      # JavaCore 线程栈可视化分析工具
├── docker/                 # Docker 容器化编排
│   ├── docker-compose.yml  # 容器编排文件（包含 app、web、jifa 服务）
│   ├── nginx/              # Nginx 镜像构建与站点反代配置
│   └── php/                # PHP-FPM 镜像构建
└── data/                   # 外部数据目录（挂载至容器内部）
    ├── deskecc/            # 实际采集的生产数据（CSV / TXT / CONF 等，挂载到容器 /data/deskecc）
    │   ├── ack/            # ACK 集群、节点及 Pod 数据
    │   ├── nginx/          # Nginx 实例与配置文件
    │   ├── sofamq/         # SOFAMQ 集群数据
    │   └── tomcat/         # Tomcat 实例数据
    ├── jifa/               # Jifa 堆内存/HPROF 诊断数据持久化目录
    ├── batch/              # 定时采集与同步脚本（独立维护，不纳入代码库）
    └── backup/             # 历史数据与周期备份归档
```

---

## 容器化部署

### 1. 准备数据目录
在项目根目录下创建对应数据目录结构（若不存在）：
```bash
mkdir -p data/deskecc data/jifa data/batch data/backup
```

### 2. 构建镜像并启动容器
```bash
# 1. 构建 Nginx 镜像
cd docker/nginx
docker build -t midas-nginx:1.30.4 .

# 2. 构建 PHP-FPM 镜像
cd ../php
docker build -t midas-php:8.2-fpm .

# 3. 启动服务
cd ..
docker compose up -d
```

### 3. 访问系统
* **访问入口**：`http://<宿主机IP>:9019`
* **默认账号**：用户名 `midadmin`，密码 `uspOB%0331`

---

## API / 命令行自动化调用

系统中的文本和配置查看接口（如 `view_txt.php`、`view_conf.php`、`view_sofamq.php`、`viewvpc_txt.php`）已全面支持命令行与自动化调用：

### 1. cURL 命令行直接获取纯文本
通过 `-u` 传递认证凭据，服务端会自动识别并直接返回干净的 `text/plain` 纯文本，免去 HTML 标签解析：

```bash
# 查看指定节点的 Pod 文本数据
curl -u midadmin:'uspOB%0331' "http://<宿主机IP>:9019/view_txt.php?file=cn-shanghai-bocom-d01.12.5.19.187.txt"

# 查看 Nginx 配置文件
curl -u midadmin:'uspOB%0331' "http://<宿主机IP>:9019/view_conf.php?file=221a04004.cloud.a04.am221_12.240.58.234_47488.conf"
```
> **提示**：密码中包含特殊字符 `%`，在 Shell 中建议用单引号将密码或参数整体包裹（如 `'uspOB%0331'`），防止被终端转义。

### 2. Python 自动化调用示例
```python
import requests

url = "http://<宿主机IP>:9019/view_txt.php"
params = {"file": "cn-shanghai-bocom-d01.12.5.19.187.txt"}
auth = ("midadmin", "uspOB%0331")

response = requests.get(url, params=params, auth=auth)
if response.status_code == 200:
    print(response.text)
else:
    print(f"请求失败: {response.status_code}")
```

---

## 后续规划 (Roadmap)

- [x] 全局登录鉴权与 API Basic Auth 认证支持
- [x] 展示各 ECS 中运行的容器与 Pod 信息
- [x] 多云 ACK 集群与节点信息聚合
- [x] SOFAMQ / Nginx / Tomcat 运行状态与配置查看
- [x] VSW 网段匹配与 JavaCore 线程堆栈诊断工具
- [ ] 同步 CMDB 应用维护人员信息，便于故障快速寻找 A 角
- [ ] 常见中间件运维现象与应急处置知识库
