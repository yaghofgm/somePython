import numpy as np
import matplotlib.pyplot as plt
from ipywidgets import interact, FloatSlider, IntSlider

import warnings
warnings.filterwarnings("ignore")  # só pra não poluir o output com mensagens do matplotlib

# while(divida>0,months<120,divida<emprestimo*1.8):
# burro, era para usar and, senao vodce fe uma tupla que roda enquanto a tupla for verdadeira

def update_plot(emprestimo=100000, juros=0.01, salary=500):
    def update_divida(divida,salary,juros):
        divida = (divida-salary)*(1+juros)  
        if divida <0:
            divida = 0
        return divida

    # months=np.arange(120)
    divida_list = []
    total_pago_list=[]
    divida = emprestimo
    total_pago=0
    months=0
    
    while divida > 0 and months < 120 and total_pago < emprestimo*1.8:
        divida = update_divida(divida,salary,juros)
        divida_list.append(divida)
        total_pago_list.append(total_pago)
        total_pago += salary
        months += 1


    plt.figure(figsize=(8,4))
    plt.plot(range(len(divida_list)), divida_list, label="Montante Devido")
    plt.plot(range(len(total_pago_list)), total_pago_list, 'r--', label="Total Pago (acumulado)")
    plt.xlabel("Meses")
    plt.ylabel("divida Devido")
    plt.title("Evolução da divida ao Longo do Tempo")
    plt.grid(True)
    plt.show()
    if divida_list[-1] == 0:
        print(f"Divida quitada em {months} meses")
    elif months == 120:
        print(f"Divida não quitada em 120 meses. Divida restante: {divida_list[-1]}")
    else:
        print(f"Excedeu 1.8x empréstimo. Divida restante: {divida_list[-1]}")

# update_plot()
# Sliders
interact(
    update_plot,
    emprestimo=IntSlider(min=1000, max=200000, step=1000, value=100000, description="Empréstimo"),
    salary=IntSlider(min=100, max=20000, step=100, value=5000, description="Salário"),
    juros=FloatSlider(min=0.001, max=0.09, step=0.001, value=0.01, description="Juros")
)