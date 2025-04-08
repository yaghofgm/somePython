import numpy as np
import matplotlib.pyplot as plt
from ipywidgets import interact, FloatSlider, IntSlider, Dropdown

import warnings
warnings.filterwarnings("ignore")  # só pra não poluir o output com mensagens do matplotlib
import numpy as np


#emprestimo = total 4 anos de curso - beca
def test1(emprestimo, juros, GPA, sex, university, course):
    """
    Will see if the person can pay the loan in 10 years without paying more than 1.80x the initial lone

    DISCLAIMER: 
    1. We are assuming a fixed interest rate for the entire period.
    2. We are assuming a fixed monthly payment of 15% of the salary.
    
    Returns: 
    bool: True if the loan is paid according to restrictions, False otherwise
    int: months left to pay the loan
    float: expected return (total amount paid)
    float: remaining debt at the end of the period
    """
    def salary_vector_prediction(GPA, sex, university, course):
        """
        Predicts a salary vector over 10 years (120 months) with stepwise (non-continuous) growth.
        The salary increases once per year and stays fixed for 12 months.
        
        DISCLAIMERS: 
        1. The growth rate should also depend on the course and on the university, 
        but for now we will assume a fixed growth rate of 10% per year.
        2. These are based on highschool GPA, not on university GPA. Will need another dataset for the other one. 

        Parameters:
        base_salary (float): The current salary.
        
        Returns:
        float: The predicted salary_vector long 10 years.

        """
        
        def base_salary(GPA, sex, university, course):
            salary_data = {
                "University A": {
                    "Engineering": 50000,
                    "Arts": 20000,
                },
                "University B": {
                    "Engineering": 130000,
                    "Arts": 45000,
                },
            }

            gpa_data = {
                "University A": {
                    "Engineering": 3.5,
                    "Arts": 3.0,
                },
                "University B": {
                    "Engineering": 3.7,
                    "Arts": 3.2,
                },
            }

            raw_salary = salary_data.get(university, {}).get(course, 50000) #default salary is 50000
            gpa_avg = gpa_data.get(university, {}).get(course, 3.5) #default GPA is 3.5

            if sex == "male":
                beta = 0.1185
            elif sex == "female":
                beta = 0.1377
            else:
                raise ValueError("Invalid value for 'sex'. It must be either 'male' or 'female'.")

            return raw_salary * np.exp(beta * (GPA - gpa_avg)) #default default will be 50,000*e^(beta*(GPA-3.5))

        def get_annual_growth(university, course):
            growth_data = {
                "University A": {
                    "Engineering": 0.10,
                    "Arts": 0.05,
                },
                "University B": {
                    "Engineering": 0.12,
                    "Arts": 0.07,
                },
            }
            return growth_data.get(university, {}).get(course, 0.1)

        # --- Core logic ---
        initial_salary = base_salary(GPA, sex, university, course)
        annual_growth = get_annual_growth(university, course)

        salary_vector = []
        current_salary = initial_salary

        for month in range(120):
            salary_vector.append(current_salary)
            if (month + 1) % 12 == 0:  # increase salary once per year
                current_salary *= (1 + annual_growth)

        return salary_vector
    
    salary_vec = salary_vector_prediction(GPA, sex, university, course)

    def update_divida(months,divida,salary_vec,juros):
        monthly_payment = salary_vec[months] * 0.15  # e.g., 15% of income
        payment = min(monthly_payment, divida)
        divida = (divida - payment) * (1 + juros)

        return max(divida, 0)  

    # months=np.arange(120)
    divida_list = []
    total_pago_list=[]
    divida = emprestimo
    total_pago=0
    months=0
    
    while divida > 0 and months < 120 and total_pago < emprestimo*1.8:
        divida = update_divida(months,divida,salary_vec,juros)
        divida_list.append(divida)
        total_pago_list.append(total_pago)
        total_pago += salary_vec[months] * 0.15  # CORREÇÃO AQUI
        months += 1


    plt.figure(figsize=(8,4))
    plt.plot(range(len(divida_list)), divida_list, label="Montante Devido")
    plt.plot(range(len(total_pago_list)), total_pago_list, 'r--', label="Total Pago (acumulado)")
    plt.xlabel("Meses")
    plt.ylabel("divida Devido")
    plt.title("Evolução da divida ao Longo do Tempo")
    plt.grid(True)
    plt.legend()
    plt.show()

    was_paid = divida_list[-1] == 0
    months_left = max(120-months, 0)  # CORREÇÃO AQUI
    expected_return = total_pago

    # if was_paid:
    #     print(f"Divida quitada em {months} meses")
    # elif months == 120:
    #     print(f"Divida não quitada em 120 meses. Divida restante: {divida_list[-1]}")
    # else:
    #     print(f"Excedeu 1.8x empréstimo. Divida restante: {divida_list[-1]}")

    # return was_paid, months_left, expected_return
    return {
        "was_paid": was_paid,
        "months_left": months_left,
        "expected_return": expected_return,
        "remaining_debt": divida_list[-1]
    }

def interactive_test1(
    emprestimo=100000,
    juros=0.01,
    GPA=3.5,
    sex="male",
    university="University A",
    course="Engineering"
):
    result = test1(
        emprestimo=emprestimo,
        juros=juros,
        GPA=GPA,
        sex=sex,
        university=university,
        course=course
    )

    print("----- RESULTADOS -----")
    if result["was_paid"]:
        print(f"✅ Dívida quitada em {120 - result['months_left']} meses")
    else:
        print("❌ Dívida não quitada")
        if result["remaining_debt"] > 0:
            print(f"Divida restante após 10 anos: R$ {result['remaining_debt']:.2f}")
        if result["expected_return"] > emprestimo * 1.8:
            print(f"❗️Total pago excedeu 1.8x o empréstimo: R$ {result['expected_return']:.2f}")
    print(f"Total pago: R$ {result['expected_return']:.2f}")

# Interface interativa
interact(
    interactive_test1,
    emprestimo=IntSlider(min=5000, max=300000, step=5000, value=100000, description="Empréstimo"),
    juros=FloatSlider(min=0.01, max=0.05, step=0.01, value=0.01, description="Juros"),
    GPA=FloatSlider(min=2.0, max=4.0, step=0.1, value=3.5, description="GPA"),
    sex=Dropdown(options=["male", "female"], value="male", description="Sexo"),
    university=Dropdown(options=["University A", "University B"], value="University A", description="Universidade"),
    course=Dropdown(options=["Engineering", "Arts"], value="Engineering", description="Curso")
)


"""
Alguns exemplos bons:
Emrprestimo de 200,000, homem fazendo artes na universidade A com gpa de 2 demora 110 meses 
Emrprestimo de 200,000, homem fazendo engenharia na universidade A com gpa de 2 demora 50 meses
"""