import os
import glob
import pandas as pd

def merge_switches():
    all_data = []
    output_filename = "All_vsw.csv"
    
    # 获取当前目录下所有的 xlsx 文件
    files = glob.glob("*.xlsx")
    
    for file in files:
        try:
            # 使用 ExcelFile 提高读取效率
            xls = pd.ExcelFile(file)
            if '交换机' in xls.sheet_names:
                df = xls.parse('交换机')
                
                required_cols = ['实例ID', '实例名称', 'IPv4网段']
                # 校验表头
                if set(required_cols).issubset(df.columns):
                    df_extracted = df[required_cols].copy()
                    
                    # 云标识：去除扩展名
                    cloud_id = os.path.splitext(file)[0]
                    df_extracted['云标识'] = cloud_id
                    
                    all_data.append(df_extracted)
                    print(f"✅ 已提取文件: {file}")
                else:
                    print(f"⚠️ 文件 [{file}] 缺少必要列，已跳过")
        except Exception as e:
            print(f"❌ 处理文件 [{file}] 出错: {e}")

    if all_data:
        merged_df = pd.concat(all_data, ignore_index=True)
        # 保存为 CSV 格式
        merged_df.to_csv(output_filename, index=False, encoding='utf-8-sig')
        print(f"\n🚀 合并完成！结果已保存至: {output_filename}")
    else:
        print("\nℹ️ 未发现可合并的数据。")

if __name__ == "__main__":
    merge_switches()
